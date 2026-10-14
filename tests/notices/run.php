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

namespace CitOmni\DemoProvider\Tests;

use CitOmni\DemoProvider\Enum\NoticeLevel;
use CitOmni\DemoProvider\Exception\NoticeConfigException;
use CitOmni\DemoProvider\Operation\ScheduleNotice;
use CitOmni\DemoProvider\Policy\SchedulingPolicy;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\DemoProvider\Service\Notices;
use CitOmni\DemoProvider\Support\LocalTime;
use CitOmni\DemoProvider\Tests\Support\Kernel\App;

/*
 * Isolated suite for site notices: LocalTime, the decisions of SchedulingPolicy,
 * the cfg checks of the notices service, the argument guards of NoticeRepository,
 * and ScheduleNotice up to the point where it would write.
 *
 * The app double registers no services. Code that reached the database would
 * throw "Unknown app component: app->db" instead of the expected result, so these
 * cases also prove that refused input never reaches persistence. See
 * tests/notices/database.php for the cases against a real database.
 *
 * Usage:
 *   php tests/notices/run.php
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


/** Timezone of the cases; it has a daylight saving change, which LocalTime must handle. */
const ZONE = 'Europe/Copenhagen';

/** Lead limit of the cases. */
const LEAD_DAYS = 30;


// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/**
 * Create an app double with the timezone and the notices cfg, and no services.
 *
 * @param  array<string,mixed>  $notices  Overrides for demo_provider.notices.
 * @return App
 */
function app(array $notices = []): App {
	return new App([
		'locale'        => ['timezone' => ZONE],
		'demo_provider' => ['notices' => $notices + ['max_visible' => 3, 'max_lead_days' => LEAD_DAYS]],
	]);
}


/**
 * Create a local time in the cases' timezone.
 *
 * @param  string  $time  'Y-m-d H:i'.
 * @return \DateTimeImmutable
 */
function at(string $time): \DateTimeImmutable {
	return new \DateTimeImmutable($time, new \DateTimeZone(ZONE));
}


// -- 1. LocalTime ------------------------------------------------

test('LocalTime parses the typed forms as local times in the cfg timezone', function (): void {
	$time = new LocalTime(app());

	foreach (['2026-12-24 08:00', '2026-12-24T08:00', '2026-12-24 08:00:00', ' 2026-12-24T08:00:00 '] as $input) {
		$parsed = $time->parse($input);

		check($parsed !== null, 'Rejected: ' . $input);
		check($parsed->format(LocalTime::FORMAT) === '2026-12-24 08:00:00', 'Wrong time for ' . $input . ': ' . $parsed->format(LocalTime::FORMAT));
		check($parsed->getTimezone()->getName() === ZONE, 'Wrong timezone for ' . $input . ': ' . $parsed->getTimezone()->getName());
	}
});

test('LocalTime rejects impossible dates, other forms and a local time that does not exist', function (): void {
	$time = new LocalTime(app());

	// 02:30 on 29 March 2026 does not exist in Copenhagen: the clocks go from 02:00 to 03:00.
	foreach (['', '2026-02-30 08:00', '2026-12-24 24:00', '24-12-2026 08:00', '2026-12-24', '2026-12-24 08:00 tomorrow', '2026-03-29 02:30'] as $input) {
		check($time->parse($input) === null, 'Accepted: ' . $input);
	}
});

test('LocalTime::now() uses the cfg timezone and falls back to UTC', function (): void {
	check((new LocalTime(app()))->now()->getTimezone()->getName() === ZONE, 'now() ignores locale.timezone');
	check((new LocalTime(new App()))->now()->getTimezone()->getName() === 'UTC', 'now() without locale.timezone is not UTC');
});


// -- 2. SchedulingPolicy -----------------------------------------
// The reason codes are compared as strings on purpose: They are public values, so a renamed
// code must fail here.

test('SchedulingPolicy allows a notice with or without an end', function (): void {
	$policy = new SchedulingPolicy(app());
	$now = at('2026-06-01 12:00');

	check($policy->refusalReasons('Maintenance tonight.', $now, null, $now) === [], 'A notice without an end was refused');
	check($policy->refusalReasons('Maintenance tonight.', at('2026-06-01 22:00'), at('2026-06-01 23:00'), $now) === [], 'A notice with an end was refused');
	check($policy->refusalReasons('Started an hour ago.', at('2026-06-01 11:00'), at('2026-06-01 13:00'), $now) === [], 'A start in the past was refused');
});

test('SchedulingPolicy refuses an empty or too long text', function (): void {
	$policy = new SchedulingPolicy(app());
	$now = at('2026-06-01 12:00');

	check($policy->refusalReasons(" \t\n", $now, null, $now) === ['text_empty'], 'Whitespace was not text_empty');

	// The limit counts characters, not bytes: 500 two-byte characters are allowed.
	check($policy->refusalReasons(\str_repeat('æ', NoticeRepository::TEXT_MAX_LENGTH), $now, null, $now) === [], 'A text at the limit was refused');
	check($policy->refusalReasons(\str_repeat('æ', NoticeRepository::TEXT_MAX_LENGTH + 1), $now, null, $now) === ['text_too_long'], 'A text above the limit was not text_too_long');
});

test('SchedulingPolicy refuses a start beyond max_lead_days with starts_too_late', function (): void {
	$policy = new SchedulingPolicy(app());
	$now = at('2026-06-01 12:00');
	$limit = $now->modify('+' . LEAD_DAYS . ' days');

	check($policy->refusalReasons('Text', $limit, null, $now) === [], 'A start at exactly the lead limit was refused');
	check($policy->refusalReasons('Text', $limit->modify('+1 minute'), null, $now) === ['starts_too_late'], 'A start after the lead limit was not starts_too_late');
});

test('SchedulingPolicy refuses an end that is not after the start or not after now', function (): void {
	$policy = new SchedulingPolicy(app());
	$now = at('2026-06-01 12:00');

	check($policy->refusalReasons('Text', at('2026-06-02 08:00'), at('2026-06-02 08:00'), $now) === ['ends_not_after_start'], 'An end equal to the start was not ends_not_after_start');
	check($policy->refusalReasons('Text', at('2026-06-01 10:00'), $now, $now) === ['ends_in_past'], 'An end equal to now was not ends_in_past');
});

test('SchedulingPolicy lists every reason, in check order', function (): void {
	$reasons = (new SchedulingPolicy(app()))->refusalReasons('', at('2026-08-01 12:00'), at('2026-05-01 12:00'), at('2026-06-01 12:00'));

	check($reasons === ['text_empty', 'starts_too_late', 'ends_not_after_start', 'ends_in_past'], 'Got: ' . \implode(', ', $reasons));
});

test('SchedulingPolicy reads the lead limit from cfg demo_provider.notices.max_lead_days', function (): void {
	$now = at('2026-06-01 12:00');

	check((new SchedulingPolicy(app(['max_lead_days' => 0])))->refusalReasons('Text', $now->modify('+1 minute'), null, $now) === ['starts_too_late'], 'max_lead_days 0 allowed a later start');
	check((new SchedulingPolicy(app(['max_lead_days' => 0])))->refusalReasons('Text', $now, null, $now) === [], 'max_lead_days 0 refused a start now');

	foreach ([-1, '30', null] as $value) {
		$e = throws(NoticeConfigException::class, fn() => (new SchedulingPolicy(app(['max_lead_days' => $value])))->refusalReasons('Text', $now, null, $now));
		check($e->path === 'demo_provider.notices.max_lead_days', 'Wrong cfg path: ' . $e->path);
	}
});


// -- 3. Notices service ------------------------------------------

test('The notices service rejects an invalid max_visible before it reaches the database', function (): void {
	foreach ([0, '3', null] as $value) {
		$e = throws(NoticeConfigException::class, fn() => new Notices(app(['max_visible' => $value])));
		check($e->path === 'demo_provider.notices.max_visible', 'Wrong cfg path: ' . $e->path);
	}
});


// -- 4. NoticeRepository guards ----------------------------------

test('NoticeRepository rejects ids and limits below 1 before it reaches the database', function (): void {
	$notices = new NoticeRepository(new App());

	throws(\InvalidArgumentException::class, fn() => $notices->listActive(0));
	throws(\InvalidArgumentException::class, fn() => $notices->listNotEnded(0));
	throws(\InvalidArgumentException::class, fn() => $notices->expire(0));
	throws(\InvalidArgumentException::class, fn() => $notices->expire(-1));
});

test('NoticeRepository rejects an empty or too long text before it reaches the database', function (): void {
	$notices = new NoticeRepository(new App());
	$now = at('2026-06-01 12:00');

	throws(\InvalidArgumentException::class, fn() => $notices->insert('', NoticeLevel::Info, $now, null));
	throws(\InvalidArgumentException::class, fn() => $notices->insert(" \t ", NoticeLevel::Info, $now, null));
	throws(\InvalidArgumentException::class, fn() => $notices->insert(\str_repeat('æ', NoticeRepository::TEXT_MAX_LENGTH + 1), NoticeLevel::Info, $now, null));
});


// -- 5. ScheduleNotice -------------------------------------------
// The cases use times relative to the real clock, because the Operation reads the clock
// itself.

test('ScheduleNotice::preflight() reports the notice as it would be stored, in the app timezone', function (): void {
	$startsAt = (new \DateTimeImmutable('+1 day', new \DateTimeZone('UTC')))->setTime(10, 0);
	$report = (new ScheduleNotice(app()))->preflight("  Planned maintenance.  ", NoticeLevel::Warning, $startsAt, $startsAt->modify('+2 hours'));

	check($report['allowed'] === true && $report['reasons'] === [], 'Refused: ' . \implode(', ', $report['reasons']));
	check($report['notice'] === [
		'level'     => 'warning',
		'text'      => 'Planned maintenance.',
		'starts_at' => $startsAt->setTimezone(new \DateTimeZone(ZONE))->format(LocalTime::FORMAT),
		'ends_at'   => $startsAt->modify('+2 hours')->setTimezone(new \DateTimeZone(ZONE))->format(LocalTime::FORMAT),
	], 'Wrong notice: ' . \json_encode($report['notice']));
});

test('ScheduleNotice::preflight() reports the reasons of SchedulingPolicy', function (): void {
	$now = new \DateTimeImmutable('now', new \DateTimeZone(ZONE));
	$report = (new ScheduleNotice(app()))->preflight('', NoticeLevel::Info, $now, $now->modify('-1 hour'));

	check($report['allowed'] === false, 'A refused notice was allowed');
	check($report['reasons'] === ['text_empty', 'ends_not_after_start', 'ends_in_past'], 'Got: ' . \implode(', ', $report['reasons']));
	check($report['notice']['ends_at'] !== null, 'The report left out the end');
});

test('ScheduleNotice refuses with the reasons of SchedulingPolicy and writes nothing', function (): void {
	$now = new \DateTimeImmutable('now', new \DateTimeZone(ZONE));
	$result = (new ScheduleNotice(app()))->execute('Text', NoticeLevel::Info, $now->modify('+' . (LEAD_DAYS + 1) . ' days'), null);

	check($result === ['status' => 'refused', 'notice_id' => null, 'reasons' => ['starts_too_late']], 'Got: ' . \json_encode($result));
});

done();
