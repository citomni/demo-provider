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

namespace CitOmni\DemoProvider\Tests\Support\Kernel;

/**
 * BaseRepository: Test double for CitOmni\Kernel\Repository\BaseRepository.
 *
 * Gives Repositories under test the constructor contract of the kernel base without loading
 * citomni/kernel.
 *
 * Rules:
 * - Mirror the kernel base wherever code under test can tell.
 */
abstract class BaseRepository {

	/** The app the Repository works with. */
	protected App $app;


	/**
	 * Keep the app, then run the subclass's init() when it has one.
	 *
	 * @param  App  $app  App double.
	 * @see \CitOmni\Kernel\Repository\BaseRepository::__construct()  The constructor this mirrors.
	 */
	public function __construct(App $app) {
		$this->app = $app;

		if (\method_exists($this, 'init')) {
			$this->init();
		}
	}

}
