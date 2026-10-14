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

use CitOmni\DemoProvider\Enum\NoticeLevel;
use CitOmni\DemoProvider\Operation\ScheduleNotice;
use CitOmni\DemoProvider\Policy\SchedulingPolicy;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\DemoProvider\Support\LocalTime;
use CitOmni\Kernel\Controller\BaseController;


/**
 * NoticeManagementController: HTTP adapter for the page where notices are scheduled and ended.
 *
 * Gives operators a browser form for the notice schedule. It decides how a result is answered,
 * never whether a notice may be scheduled; that decision belongs to ScheduleNotice.
 *
 * Rules:
 * - Keep the cfg guard in init(): No action has a login of its own.
 * - Schedule only through ScheduleNotice; end notices only through NoticeRepository::expire().
 * - Answer a form post with Post/Redirect/Get to PAGE_PATH, so a reload never posts again.
 */
class NoticeManagementController extends BaseController {

	/** Path that every form post redirects to; it must equal the page route's path. */
	private const PAGE_PATH = '/notices/manage.html';

	/** Maximum number of notices on the page. */
	private const LIST_LIMIT = 100;

	/** The form field that each SchedulingPolicy reason concerns. Every REASON_* needs an entry. */
	private const REASON_FIELDS = [
		SchedulingPolicy::REASON_TEXT_EMPTY           => 'text',
		SchedulingPolicy::REASON_TEXT_TOO_LONG        => 'text',
		SchedulingPolicy::REASON_STARTS_TOO_LATE      => 'starts_at',
		SchedulingPolicy::REASON_ENDS_NOT_AFTER_START => 'ends_at',
		SchedulingPolicy::REASON_ENDS_IN_PAST         => 'ends_at',
	];


	/**
	 * Answer 404 for every action while the management page is disabled.
	 *
	 * Behavior:
	 * - 404 through httpError() unless cfg demo_provider.notices.management_enabled is true.
	 *
	 * @return void
	 * @see \CitOmni\Kernel\Controller\BaseController::__construct()  Calls it before every action.
	 */
	protected function init(): void {
		if ($this->app->cfg->demo_provider->notices->management_enabled !== true) {
			$this->app->errorHandler->httpError(404, ['reason' => 'notice_management_disabled']);
		}
	}


	/**
	 * Render the current and upcoming notices with the scheduling form.
	 *
	 * Behavior:
	 * - Renders the route's template with notices, levels (the NoticeLevel values),
	 *   text_max_length and the pulled flash data.
	 *
	 * @return void
	 * @see \CitOmni\DemoProvider\Repository\NoticeRepository::listNotEnded()  The rows in notices.
	 */
	public function page(): void {
		$this->app->tplEngine->render($this->routeConfig['template_file'] . '@' . $this->routeConfig['template_layer'], [
			'notices'         => (new NoticeRepository($this->app))->listNotEnded(self::LIST_LIMIT),
			'levels'          => \array_column(NoticeLevel::cases(), 'value'),
			'text_max_length' => NoticeRepository::TEXT_MAX_LENGTH,
			'flash'           => $this->app->flash->pullAll(),
		]);
	}


	/**
	 * Schedule a notice from the form and report the outcome through flash data and a redirect.
	 *
	 * The decision belongs to ScheduleNotice. This action converts the form fields and
	 * translates the result for the browser: Each refusal reason becomes an error at the field
	 * it concerns, and the typed input goes back to the form.
	 *
	 * Behavior:
	 * - An empty start means now; an empty end means until the notice is ended.
	 * - Every path ends in a 303 redirect to PAGE_PATH with exactly one flash message:
	 *     - Error when the CSRF token is rejected. Nothing is scheduled.
	 *     - Success when the notice was scheduled.
	 *     - Warning, with the typed input and the field errors, when a field does not convert
	 *       or ScheduleNotice refuses. Nothing is scheduled.
	 *
	 * @return never
	 * @see \CitOmni\DemoProvider\Operation\ScheduleNotice::execute()  The statuses and reasons behind the flash data.
	 */
	public function schedule(): never {
		// -- 1. Check the CSRF token -------------------------------------
		$flash = $this->app->flash;

		if (!$this->app->csrf->verify(['action' => 'notice_schedule'])) {
			// The form posts without a script, so a rejected token goes back to the page with a
			// message rather than to a 403 page.
			$flash->error($this->app->txt->get('flash_form_expired', 'notices', 'citomni/demo-provider', 'The form expired. Try again.'));
			$this->app->response->redirect(self::PAGE_PATH, 303);
		}

		// -- 2. Read the form --------------------------------------------
		$fields = [];

		foreach (['text', 'level', 'starts_at', 'ends_at'] as $field) {
			$value = $this->app->request->post($field);
			$fields[$field] = \is_string($value) ? \trim($value) : '';
		}

		// -- 3. Convert the input ----------------------------------------
		$reasons = [];
		$time = new LocalTime($this->app);

		$level = NoticeLevel::tryFrom($fields['level']);
		if ($level === null) {
			$reasons['level'][] = 'invalid_level';
		}

		$startsAt = $fields['starts_at'] === '' ? $time->now() : $time->parse($fields['starts_at']);
		if ($startsAt === null) {
			$reasons['starts_at'][] = 'invalid_datetime';
		}

		$endsAt = $fields['ends_at'] === '' ? null : $time->parse($fields['ends_at']);
		if ($endsAt === null && $fields['ends_at'] !== '') {
			$reasons['ends_at'][] = 'invalid_datetime';
		}

		// -- 4. Schedule -------------------------------------------------
		if ($reasons === []) {
			$result = (new ScheduleNotice($this->app))->execute($fields['text'], $level, $startsAt, $endsAt);

			if ($result['status'] === ScheduleNotice::RESULT_SCHEDULED) {
				$flash->success($this->app->txt->get('flash_scheduled', 'notices', 'citomni/demo-provider', 'The notice was scheduled.'));
				$this->app->response->redirect(self::PAGE_PATH, 303);
			}

			// RESULT_REFUSED is the only status left; a new status needs its own branch.
			foreach ($result['reasons'] as $reason) {
				$reasons[self::REASON_FIELDS[$reason]][] = $reason;
			}
		}

		// -- 5. Return the input and the reasons to the form -------------
		// Values for the placeholders that the reason texts may use.
		$vars = [
			'max'  => NoticeRepository::TEXT_MAX_LENGTH,
			'days' => $this->app->cfg->demo_provider->notices->max_lead_days,
		];
		$errors = [];

		foreach ($reasons as $field => $fieldReasons) {
			foreach ($fieldReasons as $reason) {
				$errors[$field][] = $this->app->txt->get('reason_' . $reason, 'notices', 'citomni/demo-provider', $reason, $vars);
			}
		}

		$flash->old($fields);
		$flash->fieldErrors($errors);
		$flash->warning($this->app->txt->get('flash_refused', 'notices', 'citomni/demo-provider', 'The notice was not scheduled. Check the marked fields.'));
		$this->app->response->redirect(self::PAGE_PATH, 303);
	}


	/**
	 * End one notice now and report the outcome through a flash message and a redirect.
	 *
	 * Ending a notice is one guarded update, so this action calls NoticeRepository directly,
	 * with no Operation in between.
	 *
	 * Behavior:
	 * - 404 through httpError() when the id is below 1.
	 * - Every other path ends in a 303 redirect to PAGE_PATH with exactly one flash message:
	 *     - Error when the CSRF token is rejected. Nothing changes.
	 *     - Success when this request ended the notice.
	 *     - Warning when the notice does not exist or has already ended.
	 *
	 * @param  string  $id  Notice id as captured by the route.
	 * @return never
	 * @see \CitOmni\DemoProvider\Repository\NoticeRepository::expire()  What ending a notice changes.
	 */
	public function expire(string $id): never {
		$noticeId = (int)$id;

		if ($noticeId < 1) {
			$this->app->errorHandler->httpError(404, ['reason' => 'notice_not_found']);
		}

		$flash = $this->app->flash;

		if (!$this->app->csrf->verify(['action' => 'notice_expire'])) {
			// The form posts without a script, so a rejected token goes back to the page with a
			// message rather than to a 403 page.
			$flash->error($this->app->txt->get('flash_form_expired', 'notices', 'citomni/demo-provider', 'The form expired. Try again.'));
			$this->app->response->redirect(self::PAGE_PATH, 303);
		}

		if ((new NoticeRepository($this->app))->expire($noticeId)) {
			$flash->success($this->app->txt->get('flash_expired', 'notices', 'citomni/demo-provider', 'The notice was ended.'));
		} else {
			$flash->warning($this->app->txt->get('flash_not_expired', 'notices', 'citomni/demo-provider', 'The notice does not exist or has already ended.'));
		}

		$this->app->response->redirect(self::PAGE_PATH, 303);
	}


}
