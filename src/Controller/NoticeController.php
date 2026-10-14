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

namespace CitOmni\DemoProvider\Controller;

use CitOmni\Kernel\Controller\BaseController;


/**
 * NoticeController: HTTP adapter that publishes the current site notices.
 *
 * Gives scripts and other sites read access to the notices that visitors see. It shapes the
 * response; which notices are current, and how many, belongs to the notices service.
 *
 * Rules:
 * - Read only: Never change a notice.
 * - Publish only what every visitor may see, and only through the notices service.
 */
class NoticeController extends BaseController {

	/**
	 * Run setup and guards that every action needs.
	 *
	 * Notes:
	 * - Holds no access guard, so each action is exactly as exposed as its route. A guard added
	 *   here applies to every action.
	 *
	 * @return void
	 * @see \CitOmni\Kernel\Controller\BaseController::__construct()  Calls it before every action.
	 */
	protected function init(): void {
	}


	/**
	 * Answer the current notices as JSON.
	 *
	 * Behavior:
	 * - 200 with {"ok": true, "result": [{id, level, text, starts_at, ends_at}, ...]}; an empty
	 *   list when no notice is current.
	 *
	 * @return never
	 * @see \CitOmni\DemoProvider\Service\Notices::active()  Which notices are in result, and their order.
	 */
	public function activeJson(): never {
		$notices = [];

		// Fields picked by name, so a new Repository column does not reach the wire.
		foreach ($this->app->notices->active() as $notice) {
			$notices[] = [
				'id'        => $notice['id'],
				'level'     => $notice['level'],
				'text'      => $notice['text'],
				'starts_at' => $notice['starts_at'],
				'ends_at'   => $notice['ends_at'],
			];
		}

		$this->app->response->json([
			'ok'     => true,
			'result' => $notices,
		]);
	}


}
