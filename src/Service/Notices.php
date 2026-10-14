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

namespace CitOmni\DemoProvider\Service;

use CitOmni\DemoProvider\Exception\NoticeConfigException;
use CitOmni\DemoProvider\Repository\NoticeRepository;
use CitOmni\Kernel\Service\BaseService;


/**
 * Notices: Service that answers which site notices visitors see right now.
 *
 * The package's read API for current notices, for templates, endpoints and the host app's own
 * code. It applies the visible limit; the query belongs to NoticeRepository.
 *
 * Rules:
 * - Keep no copy of the notices. Read the database in every call, so a notice that is
 *   scheduled or ended shows at once, also in a long-running CLI process.
 * - Check cfg values once, in init(), and report a wrong one with NoticeConfigException.
 */
class Notices extends BaseService {

	/** Maximum number of current notices that visitors see at once. */
	private int $maxVisible;


	/**
	 * Read and check the visible limit once.
	 *
	 * @return void
	 * @throws \CitOmni\DemoProvider\Exception\NoticeConfigException  When cfg demo_provider.notices.max_visible is not an integer >= 1.
	 * @see \CitOmni\Kernel\Service\BaseService::__construct()  Calls it once per service instance.
	 */
	protected function init(): void {
		$maxVisible = $this->app->cfg->demo_provider->notices->max_visible;

		if (!\is_int($maxVisible) || $maxVisible < 1) {
			throw new NoticeConfigException('demo_provider.notices.max_visible', 'an integer >= 1', $maxVisible);
		}

		$this->maxVisible = $maxVisible;
	}


	/**
	 * List the notices that are current now, up to the visible limit.
	 *
	 * Typical usage:
	 *   $notices = $this->app->notices->active();
	 *
	 * @return list<array{id:int,level:string,text:string,starts_at:string,ends_at:?string}>  Rows; level is a NoticeLevel value.
	 * @see \CitOmni\DemoProvider\Repository\NoticeRepository::listActive()  Which notices are current, and their order.
	 */
	public function active(): array {
		return (new NoticeRepository($this->app))->listActive($this->maxVisible);
	}


}
