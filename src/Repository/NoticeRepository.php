<?php
declare(strict_types=1);
/*
 * This file is part of the CitOmni framework.
 * Low overhead, high performance, ready for anything.
 *
 * For more information, visit https://github.com/citomni
 *
 * Copyright (c) 2012-present Lars Grove Mortensen
 * SPDX-License-Identifier: MIT
 *
 * For full copyright, trademark, and license information,
 * please see the LICENSE file distributed with this source code.
 */

namespace CitOmni\DemoProvider\Repository;

use CitOmni\DemoProvider\Enum\NoticeLevel;
use CitOmni\Kernel\Repository\BaseRepository;


/**
 * NoticeRepository: Repository for site notices in demoprov_notices.
 *
 * Owns the SQL for site notices and with it the meaning of a current notice, so every caller
 * agrees on it. Whether a notice may be scheduled belongs to SchedulingPolicy.
 *
 * Rules:
 * - A notice is current from starts_at until ends_at; a null ends_at means until it is ended.
 * - Judge now with the database clock, NOW().
 * - Format every stored time with toColumn().
 * - Return datetimes as 'Y-m-d H:i:s' strings.
 */
class NoticeRepository extends BaseRepository {

	/** Maximum notice length in characters. It must equal the length of demoprov_notices.text. */
	public const TEXT_MAX_LENGTH = 500;

	/** Format of a datetime column. */
	private const DATETIME_FORMAT = 'Y-m-d H:i:s';


	/**
	 * Insert one notice and return its id.
	 *
	 * Stores the notice as given, with the text trimmed. Whether it may be scheduled is the
	 * caller's decision: This method checks the text, never the times.
	 *
	 * @param  string  $text  Notice text; 1 to TEXT_MAX_LENGTH characters after trimming.
	 * @param  NoticeLevel  $level
	 * @param  \DateTimeImmutable  $startsAt  Start, in any timezone.
	 * @param  \DateTimeImmutable|null  $endsAt  End, in any timezone, or null for no end.
	 * @return int  New notice id.
	 * @throws \InvalidArgumentException  When the trimmed text is empty or longer than TEXT_MAX_LENGTH characters.
	 * @see \CitOmni\DemoProvider\Policy\SchedulingPolicy::refusalReasons()  The checks a caller runs first.
	 */
	public function insert(string $text, NoticeLevel $level, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): int {
		$text = \trim($text);

		if ($text === '') {
			throw new \InvalidArgumentException('Notice text cannot be empty.');
		}

		if (\mb_strlen($text) > self::TEXT_MAX_LENGTH) {
			throw new \InvalidArgumentException('Notice text cannot be longer than ' . self::TEXT_MAX_LENGTH . ' characters.');
		}

		return $this->app->db->insert('demoprov_notices', [
			'level'     => $level->value,
			'text'      => $text,
			'starts_at' => self::toColumn($startsAt),
			'ends_at'   => $endsAt === null ? null : self::toColumn($endsAt),
		]);
	}


	/**
	 * List the current notices.
	 *
	 * Behavior:
	 * - Newest start first; equal starts by descending id.
	 *
	 * @param  int  $limit  Maximum number of notices, at least 1.
	 * @return list<array{id:int,level:string,text:string,starts_at:string,ends_at:?string}>  Rows; level is a NoticeLevel value.
	 * @throws \InvalidArgumentException  When the limit is below 1.
	 */
	public function listActive(int $limit): array {
		if ($limit < 1) {
			throw new \InvalidArgumentException('Limit must be >= 1.');
		}

		$rows = $this->app->db->fetchAll(
			'SELECT id, level, text, starts_at, ends_at
			 FROM demoprov_notices
			 WHERE starts_at <= NOW() AND (ends_at IS NULL OR ends_at > NOW())
			 ORDER BY starts_at DESC, id DESC
			 LIMIT ?',
			[$limit]
		);

		$notices = [];

		foreach ($rows as $row) {
			$notices[] = self::castRow($row);
		}

		return $notices;
	}


	/**
	 * List the current and upcoming notices.
	 *
	 * Behavior:
	 * - Earliest start first; equal starts by ascending id.
	 * - current is false for a notice that has not started.
	 *
	 * @param  int  $limit  Maximum number of notices, at least 1.
	 * @return list<array{id:int,level:string,text:string,starts_at:string,ends_at:?string,current:bool}>  Rows; level is a NoticeLevel value.
	 * @throws \InvalidArgumentException  When the limit is below 1.
	 */
	public function listNotEnded(int $limit): array {
		if ($limit < 1) {
			throw new \InvalidArgumentException('Limit must be >= 1.');
		}

		$rows = $this->app->db->fetchAll(
			'SELECT id, level, text, starts_at, ends_at, starts_at <= NOW() AS is_current
			 FROM demoprov_notices
			 WHERE ends_at IS NULL OR ends_at > NOW()
			 ORDER BY starts_at, id
			 LIMIT ?',
			[$limit]
		);

		$notices = [];

		foreach ($rows as $row) {
			$notices[] = self::castRow($row) + ['current' => (int)$row['is_current'] === 1];
		}

		return $notices;
	}


	/**
	 * End one current or upcoming notice now.
	 *
	 * Ends the notice at the database clock's now, so every caller sees it as ended at once.
	 * Ending is guarded: A notice ends once, also under concurrent calls.
	 *
	 * Behavior:
	 * - Sets ends_at to now. A notice that has not started gets now as its start too, so it
	 *   never shows and its start stays at or before its end.
	 * - Changes only a notice that has not ended, so of two concurrent calls only one returns true.
	 *
	 * @param  int  $noticeId
	 * @return bool  True when this call ended the notice; false when it does not exist or has already ended.
	 * @throws \InvalidArgumentException  When the id is below 1.
	 */
	public function expire(int $noticeId): bool {
		if ($noticeId < 1) {
			throw new \InvalidArgumentException('Notice id must be >= 1.');
		}

		$updated = $this->app->db->execute(
			'UPDATE demoprov_notices
			 SET ends_at = NOW(), starts_at = LEAST(starts_at, NOW())
			 WHERE id = ? AND (ends_at IS NULL OR ends_at > NOW())',
			[$noticeId]
		);

		return $updated === 1;
	}


	/**
	 * Format a time as a local datetime in PHP's timezone.
	 *
	 * Notes:
	 * - Assumes that the database session uses PHP's timezone, so a stored time compares
	 *   correctly with NOW(). An app that sets cfg db.timezone must set the zone of
	 *   locale.timezone.
	 *
	 * @param  \DateTimeImmutable  $time  Time in any timezone.
	 * @return string  'Y-m-d H:i:s'.
	 * @see \CitOmni\Infrastructure\Service\Db  How the session time zone is chosen.
	 */
	private static function toColumn(\DateTimeImmutable $time): string {
		return $time->setTimezone(new \DateTimeZone(\date_default_timezone_get()))->format(self::DATETIME_FORMAT);
	}


	/**
	 * Cast one raw row to the row shape of this Repository.
	 *
	 * @param  array<string,mixed>  $row  Raw row.
	 * @return array{id:int,level:string,text:string,starts_at:string,ends_at:?string}
	 */
	private static function castRow(array $row): array {
		return [
			'id'        => (int)$row['id'],
			'level'     => (string)$row['level'],
			'text'      => (string)$row['text'],
			'starts_at' => (string)$row['starts_at'],
			'ends_at'   => $row['ends_at'] === null ? null : (string)$row['ends_at'],
		];
	}


}
