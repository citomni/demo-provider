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
	'notices_label' => 'Driftsbeskeder',

	// Levels
	'level_info'    => 'Information',
	'level_warning' => 'Advarsel',

	// Management page
	'manage_title'    => 'Driftsbeskeder',
	'manage_intro'    => 'En besked vises øverst på siderne fra sin start til sin slutning, nyeste start først.',
	'list_title'      => 'Aktuelle og kommende',
	'list_empty'      => 'Der er ingen aktuelle eller kommende beskeder.',
	'col_text'        => 'Tekst',
	'col_level'       => 'Niveau',
	'col_starts'      => 'Starter',
	'col_ends'        => 'Slutter',
	'status_current'  => 'Aktuel',
	'status_upcoming' => 'Kommende',
	'no_end'          => 'Indtil den afsluttes',
	'expire'          => 'Afslut nu',
	'date_pattern'    => 'd. MMM y, HH:mm',

	// Scheduling form
	'form_title'   => 'Planlæg en besked',
	'label_text'   => 'Tekst',
	'label_level'  => 'Niveau',
	'label_starts' => 'Starter',
	'hint_starts'  => 'Lad feltet stå tomt for at starte nu.',
	'label_ends'   => 'Slutter',
	'hint_ends'    => 'Lad feltet stå tomt for at vise beskeden, indtil den afsluttes.',
	'schedule'     => 'Planlæg',

	// Flash messages
	'flash_scheduled'    => 'Beskeden blev planlagt.',
	'flash_refused'      => 'Beskeden blev ikke planlagt. Tjek de markerede felter.',
	'flash_expired'      => 'Beskeden blev afsluttet.',
	'flash_not_expired'  => 'Beskeden findes ikke eller er allerede afsluttet.',
	'flash_form_expired' => 'Formularen er udløbet. Prøv igen.',

	// Field reasons
	'reason_text_empty'           => 'Skriv beskedens tekst.',
	'reason_text_too_long'        => 'Brug højst %MAX% tegn.',
	'reason_invalid_level'        => 'Vælg et niveau.',
	'reason_invalid_datetime'     => 'Angiv en gyldig dato og tid.',
	'reason_starts_too_late'      => 'En besked kan højst starte om %DAYS% dage.',
	'reason_ends_not_after_start' => 'Slutningen skal ligge efter starten.',
	'reason_ends_in_past'         => 'Slutningen er allerede passeret.',

];
