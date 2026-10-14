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

namespace CitOmni\DemoProvider\Support;

use CitOmni\Kernel\App;


/**
 * LocalTime: Support class for the current time and typed times in the app's timezone.
 *
 * Gives every adapter one meaning for now and for a time that a person types. It is Support,
 * not Util, because it reads cfg and the clock. Formatting a time for display belongs to the
 * caller.
 *
 * Rules:
 * - Return \DateTimeImmutable in the timezone of cfg locale.timezone, or UTC without it.
 */
class LocalTime {

	/** Format of a stored or reported datetime, as in a datetime column. */
	public const FORMAT = 'Y-m-d H:i:s';

	/** Formats a person may type: With a space or the T of a datetime-local input, seconds optional. */
	private const INPUT_FORMATS = ['Y-m-d H:i', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s'];

	/** Timezone of cfg locale.timezone, or UTC. */
	private readonly \DateTimeZone $zone;


	/**
	 * Read the app's timezone.
	 *
	 * @param  App  $app  App whose cfg may hold locale.timezone.
	 * @throws \DateInvalidTimeZoneException  When cfg locale.timezone is not a valid timezone.
	 * @see \CitOmni\Kernel\Runtime::configure()  Gives PHP its timezone from the same key.
	 */
	public function __construct(App $app) {
		$this->zone = new \DateTimeZone((string)($app->cfg->locale->timezone ?? 'UTC'));
	}


	/**
	 * Return the current time.
	 *
	 * @return \DateTimeImmutable  Now, in the app's timezone.
	 */
	public function now(): \DateTimeImmutable {
		return new \DateTimeImmutable('now', $this->zone);
	}


	/**
	 * Parse a date and time that a person typed.
	 *
	 * Behavior:
	 * - Accepts the INPUT_FORMATS exactly; surrounding whitespace is ignored.
	 * - Returns null for anything else, including impossible dates and a local time that
	 *   does not exist because the clocks move forward that night.
	 *
	 * Typical usage:
	 *   $startsAt = (new LocalTime($this->app))->parse($input);
	 *
	 * @param  string  $input  Typed date and time.
	 * @return \DateTimeImmutable|null  The time in the app's timezone, or null when the input is not a valid local time.
	 */
	public function parse(string $input): ?\DateTimeImmutable {
		$input = \trim($input);

		foreach (self::INPUT_FORMATS as $format) {
			$time = \DateTimeImmutable::createFromFormat('!' . $format, $input, $this->zone);

			// The round trip rejects overflow such as 2026-02-30 and skipped local times.
			if ($time !== false && $time->format($format) === $input) {
				return $time;
			}
		}

		return null;
	}


}
