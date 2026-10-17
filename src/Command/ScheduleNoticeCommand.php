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

namespace CitOmni\DemoProvider\Command;

use CitOmni\DemoProvider\Enum\NoticeLevel;
use CitOmni\DemoProvider\Operation\ScheduleNotice;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\DemoProvider\Support\LocalTime;
use CitOmni\Kernel\Command\BaseCommand;


/**
 * ScheduleNoticeCommand: CLI adapter that schedules a site notice.
 *
 * Lets operators and scripts schedule notices from the terminal, with the same rules and
 * reasons as any other adapter. It converts the typed input and reports the result; whether
 * a notice may be scheduled belongs to ScheduleNotice.
 *
 * Rules:
 * - Schedule only through ScheduleNotice, also for a dry run.
 * - Convert typed times only with LocalTime::parse().
 * - With --json, write exactly one JSON document to stdout for every SUCCESS or FAILURE, and
 *   nothing else.
 */
class ScheduleNoticeCommand extends BaseCommand {

	/**
	 * Declare the command interface.
	 *
	 * @return array{arguments?: array<string,array<string,mixed>>, options?: array<string,array<string,mixed>>}
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'arguments' => [
				'text' => [
					'description' => 'Notice text, 1 to ' . NoticeRepository::TEXT_MAX_LENGTH . ' characters',
					'required'    => true,
				],
			],
			'options' => [
				'level' => [
					'short'       => 'l',
					'type'        => 'string',
					'description' => 'Level',
					'default'     => NoticeLevel::Info->value,
					'allowed'     => \array_column(NoticeLevel::cases(), 'value'),
				],
				'starts' => [
					'short'       => 's',
					'type'        => 'string',
					'description' => 'Start, like "2026-12-24 08:00"; now when left out',
				],
				'ends' => [
					'short'       => 'e',
					'type'        => 'string',
					'description' => 'End, like "2026-12-24 23:00"; none when left out',
				],
				'dry-run' => [
					'short'       => 'n',
					'type'        => 'bool',
					'description' => 'Check the notice and show it without storing it',
				],
				'json' => [
					'short'       => 'j',
					'type'        => 'bool',
					'description' => 'Emit the result as JSON',
				],
			],
		];
	}


	/**
	 * Schedule the notice, or with --dry-run report what would happen.
	 *
	 * Converts the typed input and leaves the decision to ScheduleNotice. A dry run asks
	 * ScheduleNotice::preflight() and stores nothing.
	 *
	 * Behavior:
	 * - Without --starts the notice starts now; without --ends it shows until it is ended.
	 * - Exit code 0 when the notice is scheduled, or with --dry-run would be.
	 * - Exit code 1 when ScheduleNotice refuses. The reasons go to stderr, or into the JSON.
	 * - Exit code 2 when --starts or --ends is not a valid local time. Nothing is stored.
	 * - With --json: One JSON line on stdout with ok, which is true exactly when the exit code
	 *   is 0, and result, which holds the result of ScheduleNotice, or with --dry-run its
	 *   report. A usage error writes no JSON.
	 *
	 * @return int  Process exit code.
	 * @see \CitOmni\DemoProvider\Operation\ScheduleNotice::execute()  The result behind the exit code and the JSON.
	 * @see \CitOmni\DemoProvider\Support\LocalTime::parse()  The time forms that convert.
	 */
	protected function execute(): int {
		$time = new LocalTime($this->app);

		// Optional options without a default are null when left out.
		$starts = $this->opt('starts');
		$startsAt = $starts === null ? $time->now() : $time->parse($starts);

		if ($startsAt === null) {
			$this->error('--starts is not a valid local time. Use the form "2026-12-24 08:00".');

			return self::USAGE;
		}

		$ends = $this->opt('ends');
		$endsAt = $ends === null ? null : $time->parse($ends);

		if ($endsAt === null && $ends !== null) {
			$this->error('--ends is not a valid local time. Use the form "2026-12-24 23:00".');

			return self::USAGE;
		}

		// from(), not tryFrom(): The parser has already checked the value against 'allowed'.
		$level = NoticeLevel::from($this->getString('level'));
		$text = $this->argString('text');
		$operation = new ScheduleNotice($this->app);

		if ($this->getBool('dry-run')) {
			return $this->report($operation->preflight($text, $level, $startsAt, $endsAt));
		}

		$result = $operation->execute($text, $level, $startsAt, $endsAt);
		$scheduled = $result['status'] === ScheduleNotice::RESULT_SCHEDULED;

		if ($this->getBool('json')) {
			$this->stdout(\json_encode([
				'ok'     => $scheduled,
				'result' => $result,
			], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

			return $scheduled ? self::SUCCESS : self::FAILURE;
		}

		if (!$scheduled) {
			$this->error('The notice was not scheduled: ' . \implode(', ', $result['reasons']) . '.');

			return self::FAILURE;
		}

		$this->success('Notice ' . $result['notice_id'] . ' scheduled.');

		return self::SUCCESS;
	}


	/**
	 * Print the preflight report and map it to an exit code.
	 *
	 * Behavior:
	 * - Exit code 0 when the report allows the notice, 1 when it does not.
	 * - With --json: One JSON line on stdout with ok, equal to allowed, and the report as result.
	 *
	 * @param  array{allowed:bool,reasons:list<string>,notice:array{level:string,text:string,starts_at:string,ends_at:?string}}  $report
	 * @return int  Process exit code.
	 * @see \CitOmni\DemoProvider\Operation\ScheduleNotice::preflight()  The report.
	 */
	private function report(array $report): int {
		if ($this->getBool('json')) {
			$this->stdout(\json_encode([
				'ok'     => $report['allowed'],
				'result' => $report,
			], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

			return $report['allowed'] ? self::SUCCESS : self::FAILURE;
		}

		if (!$report['allowed']) {
			$this->error('The notice would be refused: ' . \implode(', ', $report['reasons']) . '.');

			return self::FAILURE;
		}

		$notice = $report['notice'];
		$this->info('Dry run. The notice would be scheduled:');
		$this->stdout('  ' . $notice['level'] . ', from ' . $notice['starts_at'] . ' ' . ($notice['ends_at'] === null ? 'until ended' : 'to ' . $notice['ends_at']));
		$this->stdout('  ' . $notice['text']);

		return self::SUCCESS;
	}


}
