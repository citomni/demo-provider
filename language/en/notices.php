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

return [

	// Site notices on every page
	'notices_label' => 'Site notices',

	// Levels
	'level_info'    => 'Information',
	'level_warning' => 'Warning',

	// Management page
	'manage_title'    => 'Site notices',
	'manage_intro'    => 'A notice shows at the top of the pages from its start until its end, newest start first.',
	'list_title'      => 'Current and upcoming',
	'list_empty'      => 'There are no current or upcoming notices.',
	'col_text'        => 'Text',
	'col_level'       => 'Level',
	'col_starts'      => 'Starts',
	'col_ends'        => 'Ends',
	'status_current'  => 'Current',
	'status_upcoming' => 'Upcoming',
	'no_end'          => 'Until ended',
	'expire'          => 'End now',
	'date_pattern'    => 'd MMM y, HH:mm',

	// Scheduling form
	'form_title'   => 'Schedule a notice',
	'label_text'   => 'Text',
	'label_level'  => 'Level',
	'label_starts' => 'Starts',
	'hint_starts'  => 'Leave empty to start now.',
	'label_ends'   => 'Ends',
	'hint_ends'    => 'Leave empty to show the notice until it is ended.',
	'schedule'     => 'Schedule',

	// Flash messages
	'flash_scheduled'    => 'The notice was scheduled.',
	'flash_refused'      => 'The notice was not scheduled. Check the marked fields.',
	'flash_expired'      => 'The notice was ended.',
	'flash_not_expired'  => 'The notice does not exist or has already ended.',
	'flash_form_expired' => 'The form expired. Try again.',

	// Field reasons
	'reason_text_empty'           => 'Write the text of the notice.',
	'reason_text_too_long'        => 'Use at most %MAX% characters.',
	'reason_invalid_level'        => 'Choose a level.',
	'reason_invalid_datetime'     => 'Enter a valid date and time.',
	'reason_starts_too_late'      => 'A notice can start at most %DAYS% days from now.',
	'reason_ends_not_after_start' => 'The end must come after the start.',
	'reason_ends_in_past'         => 'The end has already passed.',

];
