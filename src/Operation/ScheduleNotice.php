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

namespace CitOmni\DemoProvider\Operation;

use CitOmni\DemoProvider\Enum\NoticeLevel;
use CitOmni\DemoProvider\Policy\SchedulingPolicy;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\DemoProvider\Support\LocalTime;
use CitOmni\Kernel\Operation\BaseOperation;


/**
 * ScheduleNotice: Operation that schedules one site notice when SchedulingPolicy allows it.
 *
 * The one place where a notice is scheduled, so every adapter applies the same rules and
 * reports the same reasons. It returns domain-shaped arrays; turning them into HTTP or CLI
 * output belongs to the adapters.
 *
 * Rules:
 * - Leave every scheduling rule to SchedulingPolicy, and ask it in every call.
 * - Take the current time from LocalTime::now() and pass it to the Policy.
 * - Report expected outcomes as data, never as exceptions.
 */
class ScheduleNotice extends BaseOperation {

	/** The notice was stored. */
	public const RESULT_SCHEDULED = 'scheduled';

	/** SchedulingPolicy refused the notice; nothing was stored. */
	public const RESULT_REFUSED = 'refused';


	/**
	 * Report what execute() would do with the same input, without storing anything.
	 *
	 * Behavior:
	 * - allowed is true when execute() would store the notice; reasons lists why not otherwise.
	 * - notice is the notice as it would be stored: The text trimmed, the times in the app's
	 *   timezone, formatted as LocalTime::FORMAT.
	 *
	 * Notes:
	 * - Both methods read the clock, so execute() may refuse what preflight() allowed a
	 *   moment earlier.
	 *
	 * @param  string  $text  Notice text, as typed.
	 * @param  NoticeLevel  $level
	 * @param  \DateTimeImmutable  $startsAt  Start; one in the past means at once.
	 * @param  \DateTimeImmutable|null  $endsAt  End, or null for no end.
	 * @return array{allowed:bool,reasons:list<string>,notice:array{level:string,text:string,starts_at:string,ends_at:?string}}
	 * @see \CitOmni\DemoProvider\Policy\SchedulingPolicy::refusalReasons()  The values in reasons, and their order.
	 */
	public function preflight(string $text, NoticeLevel $level, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): array {
		$now = (new LocalTime($this->app))->now();
		$reasons = (new SchedulingPolicy($this->app))->refusalReasons($text, $startsAt, $endsAt, $now);
		$zone = $now->getTimezone();

		return [
			'allowed' => $reasons === [],
			'reasons' => $reasons,
			'notice'  => [
				'level'     => $level->value,
				'text'      => \trim($text),
				'starts_at' => $startsAt->setTimezone($zone)->format(LocalTime::FORMAT),
				'ends_at'   => $endsAt?->setTimezone($zone)->format(LocalTime::FORMAT),
			],
		];
	}


	/**
	 * Schedule one notice, or refuse it with the reasons of SchedulingPolicy.
	 *
	 * Stores the notice through NoticeRepository when SchedulingPolicy finds no reason to
	 * refuse it.
	 *
	 * Behavior:
	 * - RESULT_SCHEDULED: The notice is stored; notice_id is its id and reasons is empty.
	 * - RESULT_REFUSED: Nothing is stored; notice_id is null and reasons lists why.
	 *
	 * Typical usage:
	 *   $result = (new ScheduleNotice($this->app))->execute($text, NoticeLevel::Info, $startsAt, null);
	 *
	 * @param  string  $text  Notice text, as typed.
	 * @param  NoticeLevel  $level
	 * @param  \DateTimeImmutable  $startsAt  Start; one in the past means at once.
	 * @param  \DateTimeImmutable|null  $endsAt  End, or null for no end.
	 * @return array{status:string,notice_id:?int,reasons:list<string>}
	 * @see \CitOmni\DemoProvider\Policy\SchedulingPolicy::refusalReasons()  The values in reasons, and their order.
	 */
	public function execute(string $text, NoticeLevel $level, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt): array {
		$reasons = (new SchedulingPolicy($this->app))->refusalReasons($text, $startsAt, $endsAt, (new LocalTime($this->app))->now());

		if ($reasons !== []) {
			return [
				'status'    => self::RESULT_REFUSED,
				'notice_id' => null,
				'reasons'   => $reasons,
			];
		}

		return [
			'status'    => self::RESULT_SCHEDULED,
			'notice_id' => (new NoticeRepository($this->app))->insert($text, $level, $startsAt, $endsAt),
			'reasons'   => [],
		];
	}


}
