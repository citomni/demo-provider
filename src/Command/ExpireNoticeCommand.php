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
 * ExpireNoticeCommand: CLI adapter that ends one site notice now.
 *
 * Gives operators and scripts a terminal way to end a current or upcoming notice. It parses
 * the id and reports the outcome. Ending a notice is one guarded update that NoticeRepository
 * owns, so no Operation sits in between.
 *
 * Rules:
 * - End notices only through NoticeRepository::expire().
 */
class ExpireNoticeCommand extends BaseCommand {

	/**
	 * Declare the command interface.
	 *
	 * @return array<string,array<string,mixed>>
	 * @see \CitOmni\Kernel\Command\BaseCommand::signature()  The signature format.
	 */
	protected function signature(): array {
		return [
			'arguments' => [
				'id' => [
					'description' => 'Notice id, as notice:list shows it',
					'required'    => true,
					'type'        => 'int',
				],
			],
		];
	}


	/**
	 * End the notice and report the outcome.
	 *
	 * The exit code tells whether this run ended the notice, so a script can tell the run
	 * that ended it from a repeated one.
	 *
	 * Behavior:
	 * - 0 when this run ended the notice.
	 * - 1 when the notice does not exist or has already ended. Nothing changes.
	 * - 2 when the id is below 1.
	 *
	 * @return int  Process exit code.
	 * @see \CitOmni\DemoProvider\Repository\NoticeRepository::expire()  What ending a notice changes.
	 */
	protected function execute(): int {
		$noticeId = $this->argInt('id');

		if ($noticeId < 1) {
			$this->error('Notice id must be >= 1.');

			return self::USAGE;
		}

		if (!(new NoticeRepository($this->app))->expire($noticeId)) {
			$this->error('Notice ' . $noticeId . ' does not exist or has already ended.');

			return self::FAILURE;
		}

		$this->success('Notice ' . $noticeId . ' ended.');

		return self::SUCCESS;
	}


}
