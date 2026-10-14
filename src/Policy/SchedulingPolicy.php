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

namespace CitOmni\DemoProvider\Policy;

use CitOmni\DemoProvider\Exception\NoticeConfigException;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\Kernel\App;


/**
 * SchedulingPolicy: Policy that decides whether a site notice may be scheduled.
 *
 * Holds the scheduling rules in one place, so every caller refuses the same notices for the
 * same reasons. It only decides; storing a notice and presenting a refusal belong to the
 * callers.
 *
 * Rules:
 * - Express every refusal as a REASON_* value. The values are public: Never rename one.
 * - Read no clock: A method that needs the current time takes it as an argument.
 */
class SchedulingPolicy {

	/** The text is empty after trimming. */
	public const REASON_TEXT_EMPTY = 'text_empty';

	/** The trimmed text is longer than NoticeRepository::TEXT_MAX_LENGTH characters. */
	public const REASON_TEXT_TOO_LONG = 'text_too_long';

	/** The start lies more than cfg demo_provider.notices.max_lead_days days after now. */
	public const REASON_STARTS_TOO_LATE = 'starts_too_late';

	/** The end is not after the start. */
	public const REASON_ENDS_NOT_AFTER_START = 'ends_not_after_start';

	/** The end is not after now. */
	public const REASON_ENDS_IN_PAST = 'ends_in_past';


	/**
	 * Create the Policy for one app.
	 *
	 * @param  App  $app  App whose cfg holds the lead limit.
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * List the reasons that block scheduling a notice.
	 *
	 * Behavior:
	 * - An empty list means that the notice may be scheduled.
	 * - A start in the past is allowed and means at once. An end is optional.
	 *
	 * Notes:
	 * - Days are calendar days in the timezone of $now.
	 *
	 * Typical usage:
	 *   $reasons = (new SchedulingPolicy($this->app))->refusalReasons($text, $startsAt, $endsAt, $time->now());
	 *
	 * @param  string  $text  Notice text, as typed.
	 * @param  \DateTimeImmutable  $startsAt
	 * @param  \DateTimeImmutable|null  $endsAt  End, or null for no end.
	 * @param  \DateTimeImmutable  $now  The current time.
	 * @return list<string>  REASON_* values in check order.
	 * @throws \CitOmni\DemoProvider\Exception\NoticeConfigException  When cfg demo_provider.notices.max_lead_days is not an integer >= 0.
	 */
	public function refusalReasons(string $text, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt, \DateTimeImmutable $now): array {
		$maxLeadDays = $this->app->cfg->demo_provider->notices->max_lead_days;

		if (!\is_int($maxLeadDays) || $maxLeadDays < 0) {
			throw new NoticeConfigException('demo_provider.notices.max_lead_days', 'an integer >= 0', $maxLeadDays);
		}

		$reasons = [];
		$length = \mb_strlen(\trim($text));

		if ($length === 0) {
			$reasons[] = self::REASON_TEXT_EMPTY;
		} elseif ($length > NoticeRepository::TEXT_MAX_LENGTH) {
			$reasons[] = self::REASON_TEXT_TOO_LONG;
		}

		if ($startsAt > $now->modify('+' . $maxLeadDays . ' days')) {
			$reasons[] = self::REASON_STARTS_TOO_LATE;
		}

		if ($endsAt !== null) {
			if ($endsAt <= $startsAt) {
				$reasons[] = self::REASON_ENDS_NOT_AFTER_START;
			}

			if ($endsAt <= $now) {
				$reasons[] = self::REASON_ENDS_IN_PAST;
			}
		}

		return $reasons;
	}


}
