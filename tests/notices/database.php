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
use CitOmni\DemoProvider\Operation\ScheduleNotice;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\DemoProvider\Service\Notices;
use CitOmni\DemoProvider\Tests\Support\Kernel\App;
use CitOmni\DemoProvider\Tests\Support\TestDatabase;

/*
 * Database suite for site notices against a real MySQL/MariaDB server: the SQL of
 * NoticeRepository on the schema in sql/citomni_demo-provider.sql, the notices
 * service, and ScheduleNotice end to end, through the real Db service of
 * citomni/infrastructure.
 *
 * The run creates one database, citomni_demo_provider_notices_test_{random},
 * imports sql/citomni_demo-provider.sql into it and drops only that database in
 * finally. Every case starts from an empty demoprov_notices table.
 *
 * Usage:
 *   php tests/notices/database.php
 *
 * Notes:
 * - Needs the test database server; see TestDatabase for the connection and the password.
 * - Needs vendor/ from "composer install" for the Db service.
 * - Sets PHP's timezone to the cfg timezone, as an app does at boot. It assumes that the Db
 *   service derives the session time zone from it, so NOW() in SQL and the times of the
 *   cases agree.
 */

if (\PHP_SAPI !== 'cli') {
	throw new \RuntimeException('CLI only.');
}

\mysqli_report(\MYSQLI_REPORT_ERROR | \MYSQLI_REPORT_STRICT);

\set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
	throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require \dirname(__DIR__) . '/bootstrap.php';


/** Timezone of the cases, set as PHP's timezone and as cfg locale.timezone. */
const ZONE = 'Europe/Copenhagen';

/** Notices on a page in the cases' cfg. */
const MAX_VISIBLE = 2;

\date_default_timezone_set(ZONE);


// ----------------------------------------------------------------
// Helpers
// ----------------------------------------------------------------

/**
 * Create an app double with the real Db service on the run's database and the notices cfg.
 *
 * @param  string  $database  The run's database.
 * @return App
 */
function app(string $database): App {
	return TestDatabase::app($database, [
		'locale'        => ['timezone' => ZONE],
		'demo_provider' => ['notices' => ['max_visible' => MAX_VISIBLE, 'max_lead_days' => 90]],
	]);
}


/**
 * Empty demoprov_notices and restart its ids at 1.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @return void
 */
function emptyTable(\mysqli $admin): void {
	$admin->query('TRUNCATE TABLE demoprov_notices');
}


/**
 * Create a time relative to now, in PHP's timezone.
 *
 * @param  string  $modifier  Relative time, like '-1 hour'.
 * @return \DateTimeImmutable
 */
function at(string $modifier): \DateTimeImmutable {
	return new \DateTimeImmutable($modifier);
}


/**
 * Insert a notice on the admin connection, past the code under test.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @param  string  $text
 * @param  \DateTimeImmutable  $startsAt
 * @param  \DateTimeImmutable|null  $endsAt  End, or null for no end.
 * @param  string  $level  Not checked, so a case can test the schema with it.
 * @return int  New notice id.
 */
function fixture(\mysqli $admin, string $text, \DateTimeImmutable $startsAt, ?\DateTimeImmutable $endsAt, string $level = 'info'): int {
	$admin->execute_query(
		'INSERT INTO demoprov_notices (level, text, starts_at, ends_at) VALUES (?, ?, ?, ?)',
		[$level, $text, $startsAt->format('Y-m-d H:i:s'), $endsAt?->format('Y-m-d H:i:s')]
	);

	return (int)$admin->insert_id;
}


/**
 * Read one row of demoprov_notices on the admin connection.
 *
 * @param  \mysqli  $admin  The run's admin connection.
 * @param  int  $id  Notice id.
 * @return array<string,mixed>|null  Row, or null when it does not exist.
 */
function row(\mysqli $admin, int $id): ?array {
	return $admin->execute_query('SELECT id, level, text, starts_at, ends_at FROM demoprov_notices WHERE id = ?', [$id])->fetch_assoc();
}


/**
 * List the texts of the notices, in list order.
 *
 * @param  list<array{text:string}>  $notices
 * @return list<string>
 */
function texts(array $notices): array {
	return \array_column($notices, 'text');
}


// ----------------------------------------------------------------
// Run
// ----------------------------------------------------------------

$admin = TestDatabase::connect();
$database = TestDatabase::create($admin, 'notices');

try {
	$admin->select_db($database);
	TestDatabase::import($admin, (string)\file_get_contents(\dirname(__DIR__, 2) . '/sql/citomni_demo-provider.sql'));


	// -- 1. NoticeRepository -----------------------------------------

	test('insert() stores a trimmed notice with local times and returns its id', function () use ($admin, $database): void {
		emptyTable($admin);

		// A start given in UTC is stored as the local time in PHP's timezone.
		$startsAt = new \DateTimeImmutable('2026-12-24 07:00:00', new \DateTimeZone('UTC'));
		$id = (new NoticeRepository(app($database)))->insert("  Closed for Christmas.  ", NoticeLevel::Warning, $startsAt, null);
		$row = row($admin, $id);

		check($id === 1, 'Unexpected id: ' . $id);
		check($row['text'] === 'Closed for Christmas.', 'Text not trimmed: ' . \var_export($row['text'], true));
		check($row['level'] === 'warning', 'Wrong level: ' . $row['level']);
		check($row['starts_at'] === '2026-12-24 08:00:00', 'Not stored as local time: ' . $row['starts_at']);
		check($row['ends_at'] === null, 'An end was stored');
	});

	test('insert() stores a text of exactly 500 characters intact', function () use ($admin, $database): void {
		emptyTable($admin);

		$text = \str_repeat('æ', NoticeRepository::TEXT_MAX_LENGTH);
		$id = (new NoticeRepository(app($database)))->insert($text, NoticeLevel::Info, at('now'), null);

		check(row($admin, $id)['text'] === $text, 'The text was changed or cut');
	});

	test('listActive() returns only current notices, newest start first, within the limit', function () use ($admin, $database): void {
		emptyTable($admin);

		fixture($admin, 'ended', at('-3 hours'), at('-1 hour'));
		fixture($admin, 'upcoming', at('+1 hour'), null);
		fixture($admin, 'older', at('-2 hours'), at('+1 hour'));
		$openStart = at('-1 hour');
		$openId = fixture($admin, 'open-ended', $openStart, null, 'warning');
		$tie = at('-30 minutes');
		fixture($admin, 'tie-low-id', $tie, at('+1 hour'));
		fixture($admin, 'tie-high-id', $tie, at('+1 hour'));

		$notices = (new NoticeRepository(app($database)))->listActive(10);

		check(texts($notices) === ['tie-high-id', 'tie-low-id', 'open-ended', 'older'], 'Got: ' . \implode(', ', texts($notices)));
		check(texts((new NoticeRepository(app($database)))->listActive(2)) === ['tie-high-id', 'tie-low-id'], 'The limit was not applied in order');
		check($notices[2] === [
			'id'        => $openId,
			'level'     => 'warning',
			'text'      => 'open-ended',
			'starts_at' => $openStart->format('Y-m-d H:i:s'),
			'ends_at'   => null,
		], 'Wrong row shape or types: ' . \json_encode($notices[2]));
	});

	test('listNotEnded() returns current and upcoming notices by start, with the current flag', function () use ($admin, $database): void {
		emptyTable($admin);

		fixture($admin, 'ended', at('-3 hours'), at('-1 hour'));
		fixture($admin, 'later', at('+2 hours'), null);
		fixture($admin, 'current', at('-1 hour'), at('+1 hour'));
		fixture($admin, 'soon', at('+1 hour'), at('+3 hours'));

		$notices = (new NoticeRepository(app($database)))->listNotEnded(10);

		check(texts($notices) === ['current', 'soon', 'later'], 'Got: ' . \implode(', ', texts($notices)));
		check(\array_column($notices, 'current') === [true, false, false], 'Wrong current flags: ' . \json_encode(\array_column($notices, 'current')));
		check(\count((new NoticeRepository(app($database)))->listNotEnded(1)) === 1, 'The limit was not applied');
	});

	test('expire() ends a current notice once', function () use ($admin, $database): void {
		emptyTable($admin);

		$id = fixture($admin, 'current', at('-2 hours'), null);
		$notices = new NoticeRepository(app($database));

		check($notices->expire($id) === true, 'The first call did not end the notice');
		check(row($admin, $id)['ends_at'] !== null, 'ends_at was not set');
		check($notices->listActive(10) === [], 'The ended notice is still current');

		// Move the end an hour back, so a second write would change the row. Without
		// the guard, a write in the same second would leave it unchanged and report 0 rows.
		$endedAt = at('-1 hour')->format('Y-m-d H:i:s');
		$admin->execute_query('UPDATE demoprov_notices SET ends_at = ? WHERE id = ?', [$endedAt, $id]);

		check($notices->expire($id) === false, 'The second call ended it again');
		check(row($admin, $id)['ends_at'] === $endedAt, 'The second call changed ends_at');
		check($notices->expire(999) === false, 'A missing notice was reported as ended');
	});

	test('expire() of an upcoming notice moves its start to its end, so it never shows', function () use ($admin, $database): void {
		emptyTable($admin);

		$id = fixture($admin, 'upcoming', at('+1 hour'), at('+2 hours'));
		$notices = new NoticeRepository(app($database));

		check($notices->expire($id) === true, 'The upcoming notice was not ended');
		check($notices->expire($id) === false, 'The second call ended it again');

		$row = row($admin, $id);
		check($row['starts_at'] === $row['ends_at'], 'The window is not empty: ' . $row['starts_at'] . ' to ' . $row['ends_at']);
		check($row['ends_at'] <= at('+1 minute')->format('Y-m-d H:i:s'), 'ends_at is not now: ' . $row['ends_at']);
		check($notices->listNotEnded(10) === [], 'The ended notice is still upcoming');
	});

	test('The schema refuses a level that is not exactly info or warning, and an end before the start', function () use ($admin): void {
		emptyTable($admin);

		// The collation of the column ignores trailing spaces in comparisons, so only a check that
		// compares bytes refuses them.
		foreach (['urgent', 'info ', 'warning  ', ' info', 'Info', "info\t", ''] as $level) {
			$e = throws(\mysqli_sql_exception::class, fn() => fixture($admin, 'Text', at('now'), null, $level));
			check(\str_contains($e->getMessage(), 'chk_demoprov_notices_level'), \json_encode($level) . ': another error: ' . $e->getMessage());
		}

		check(row($admin, fixture($admin, 'Text', at('now'), null, 'warning'))['level'] === 'warning', 'The exact value was not stored as given');

		$e = throws(\mysqli_sql_exception::class, fn() => fixture($admin, 'Text', at('now'), at('-1 minute')));
		check(\str_contains($e->getMessage(), 'chk_demoprov_notices_window'), 'Another error: ' . $e->getMessage());
	});


	// -- 2. Notices service ------------------------------------------

	test('The notices service returns at most max_visible current notices', function () use ($admin, $database): void {
		emptyTable($admin);

		fixture($admin, 'first', at('-3 hours'), null);
		fixture($admin, 'second', at('-2 hours'), null);
		fixture($admin, 'third', at('-1 hour'), null);
		fixture($admin, 'upcoming', at('+1 hour'), null);

		$notices = (new Notices(app($database)))->active();

		check(texts($notices) === ['third', 'second'], 'Got: ' . \implode(', ', texts($notices)));
	});


	// -- 3. ScheduleNotice -------------------------------------------

	test('ScheduleNotice schedules a notice that SchedulingPolicy allows', function () use ($admin, $database): void {
		emptyTable($admin);

		$startsAt = at('+1 day');
		$result = (new ScheduleNotice(app($database)))->execute(' Planned maintenance. ', NoticeLevel::Warning, $startsAt, $startsAt->modify('+1 hour'));
		$row = row($admin, 1);

		check($result === ['status' => 'scheduled', 'notice_id' => 1, 'reasons' => []], 'Got: ' . \json_encode($result));
		check($row['text'] === 'Planned maintenance.' && $row['level'] === 'warning', 'Wrong row: ' . \json_encode($row));
		check($row['starts_at'] === $startsAt->format('Y-m-d H:i:s'), 'Wrong start: ' . $row['starts_at']);
		check($row['ends_at'] === $startsAt->modify('+1 hour')->format('Y-m-d H:i:s'), 'Wrong end: ' . $row['ends_at']);
	});

	test('ScheduleNotice refuses with the reasons of SchedulingPolicy and stores nothing', function () use ($admin, $database): void {
		emptyTable($admin);

		$result = (new ScheduleNotice(app($database)))->execute('Text', NoticeLevel::Info, at('now'), at('-1 hour'));

		check($result['status'] === 'refused', 'Got: ' . \json_encode($result));
		check($result['reasons'] === ['ends_not_after_start', 'ends_in_past'], 'Got: ' . \implode(', ', $result['reasons']));
		check((int)$admin->query('SELECT COUNT(*) FROM demoprov_notices')->fetch_row()[0] === 0, 'A refused notice was stored');
	});
} finally {
	TestDatabase::drop($admin, $database);
}

done();
