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

namespace CitOmni\DemoProvider\Enum;


/**
 * NoticeLevel: Enum of how prominently a site notice is shown.
 *
 * The one vocabulary for a notice's level, so storage, transport and display agree on it.
 * How a level looks belongs to the templates.
 *
 * Rules:
 * - Never rename or remove a backing value: The values are stored and transmitted.
 * - A new case must also be allowed by the level check of the demoprov_notices schema.
 */
enum NoticeLevel: string {

	/** General information. */
	case Info = 'info';

	/** Something visitors should act on or plan for, such as maintenance. */
	case Warning = 'warning';

}
