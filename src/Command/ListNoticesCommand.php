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

use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\Kernel\Command\BaseCommand;


/**
 * ListNoticesCommand: CLI adapter that lists the current and upcoming site notices.
 *
 * Shows operators every notice that has not ended, with its id, so they can review the
 * schedule and end a notice. It formats the list; reading it belongs to NoticeRepository.
 *
 * Rules:
 * - Read only: Never change a notice.
 * - With --json, write exactly one JSON document to stdout for every SUCCESS or FAILURE, and
 *   nothing else.
 */
class ListNoticesCommand extends BaseCommand {

	/**
	 * Declare the command interface.
	 *
	 * @return array{arguments?: array<string,array<string,mixed>>, options?: array<string,array<string,mixed>>}
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'options' => [
				'limit' => [
					'type'        => 'int',
					'description' => 'Maximum number of notices',
					'default'     => 50,
				],
				'json' => [
					'short'       => 'j',
					'type'        => 'bool',
					'description' => 'Emit the list as JSON',
				],
			],
		];
	}


	/**
	 * Print the notices that have not ended.
	 *
	 * Gives a person aligned columns and a script a JSON array, from the same rows.
	 *
	 * Behavior:
	 * - With --json: One JSON line on stdout with ok true and the rows as result, also when
	 *   there are none. A usage error writes no JSON.
	 * - Otherwise one line per row: id, current or upcoming, level, start, end and text, with
	 *   "until ended" for a notice without an end. A message instead when there are none.
	 * - Exit code 0, or 2 when --limit is below 1.
	 *
	 * @return int  Process exit code.
	 * @see \CitOmni\DemoProvider\Repository\NoticeRepository::listNotEnded()  The rows and their order.
	 */
	protected function execute(): int {
		$limit = $this->getInt('limit');

		if ($limit < 1) {
			$this->error('--limit must be >= 1.');

			return self::USAGE;
		}

		$notices = (new NoticeRepository($this->app))->listNotEnded($limit);

		if ($this->getBool('json')) {
			$this->stdout(\json_encode([
				'ok'     => true,
				'result' => $notices,
			], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

			return self::SUCCESS;
		}

		if ($notices === []) {
			$this->info('No current or upcoming notices.');

			return self::SUCCESS;
		}

		foreach ($notices as $notice) {
			$this->stdout(\sprintf(
				'%5d  %-8s  %-7s  %s -> %-19s  %s',
				$notice['id'],
				$notice['current'] ? 'current' : 'upcoming',
				$notice['level'],
				$notice['starts_at'],
				$notice['ends_at'] ?? 'until ended',
				$notice['text']
			));
		}

		return self::SUCCESS;
	}


}
