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

namespace CitOmni\DemoProvider\Boot;


/**
 * Registry: Boot contributions of citomni/demo-provider, the site notices feature.
 *
 * Declares what the package adds to a host app as constant arrays: services, cfg baselines,
 * the template layer and template variables, routes and commands. Merging them with the host
 * app's own maps, and deciding what wins, belongs to the kernel.
 *
 * Rules:
 * - Constants only: No IO, no runtime branching and no environment reads.
 * - Ship a baseline for every cfg path the package reads, with a comment on what each key
 *   controls and its unit.
 * - Ship defaults that are safe in every app. A page without a login stays off until the
 *   host app enables it.
 * - Keep names recognizable as the package's own: cfg under demo_provider, commands under
 *   notice: and citomni/demo-provider as the template layer.
 */
class Registry {

	/** Services for HTTP and CLI. */
	public const array MAP_COMMON = [
		'notices' => \CitOmni\DemoProvider\Service\Notices::class,
	];

	// Services for one mode. Not used: the notices service works in both modes.
	// public const array MAP_HTTP = [];
	// public const array MAP_CLI = [];


	/** Cfg baseline for both modes. */
	public const array CFG_COMMON = [
		'demo_provider' => [
			'notices' => [
				'max_visible'   => 3,   // Maximum number of current notices that visitors see at once.
				'max_lead_days' => 90,  // How many days ahead a notice may start.
			],
		],
	];


	/** Cfg baseline for HTTP. */
	public const array CFG_HTTP = [
		'demo_provider' => [
			'notices' => [
				// true turns on the management pages, which schedule and end notices without a
				// login. Enable them only where every visitor may do that, such as a local dev
				// machine.
				'management_enabled' => false,
			],
		],

		'view' => [
			'template_layers' => [
				'citomni/demo-provider' => \CITOMNI_APP_PATH . '/vendor/citomni/demo-provider/templates',  // The package's templates, referenced as file@citomni/demo-provider.
			],

			'vars' => [
				// $site_notices: The current notices, on every page. The source runs on each
				// page that include covers, so narrow include when only some pages show notices.
				'site_notices' => [
					'type'    => 'dynamic',
					'include' => ['*'],
					'source'  => [
						'service' => 'notices',
						'method'  => 'active',
					],
				],
			],
		],
	];


	/** HTTP routes. */
	public const array ROUTES_HTTP = [
		'/notices.json' => [
			'controller' => \CitOmni\DemoProvider\Controller\NoticeController::class,
			'action'     => 'activeJson',
			'methods'    => ['GET'],
		],

		'/notices/manage.html' => [
			'controller'     => \CitOmni\DemoProvider\Controller\NoticeManagementController::class,
			'action'         => 'page',
			'methods'        => ['GET'],
			'template_file'  => 'public/manage_notices.html',
			'template_layer' => 'citomni/demo-provider',
		],

		'/notices/schedule' => [
			'controller' => \CitOmni\DemoProvider\Controller\NoticeManagementController::class,
			'action'     => 'schedule',
			'methods'    => ['POST'],
		],

		'regex' => [
			'/notice/{id}/expire' => [
				'controller' => \CitOmni\DemoProvider\Controller\NoticeManagementController::class,
				'action'     => 'expire',
				'methods'    => ['POST'],
			],
		],
	];


	// Cfg baseline for CLI. Not used: the commands read only keys that CFG_COMMON ships.
	// public const array CFG_CLI = [];


	/** CLI commands. */
	public const array COMMANDS_CLI = [
		'notice:schedule' => [
			'command'     => \CitOmni\DemoProvider\Command\ScheduleNoticeCommand::class,
			'description' => 'Schedule a site notice.',
		],
		'notice:expire' => [
			'command'     => \CitOmni\DemoProvider\Command\ExpireNoticeCommand::class,
			'description' => 'End a current or upcoming site notice now.',
		],
		'notice:list' => [
			'command'     => \CitOmni\DemoProvider\Command\ListNoticesCommand::class,
			'description' => 'List current and upcoming site notices.',
		],
	];


}
