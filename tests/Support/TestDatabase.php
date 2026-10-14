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

namespace CitOmni\DemoProvider\Tests\Support;

use CitOmni\DemoProvider\Tests\Support\Kernel\App;
use CitOmni\Infrastructure\Service\Db;

/**
 * TestDatabase: Test support for the disposable database of a database suite.
 *
 * Gives each database suite its own database on the test server, and an App double with the
 * real Db service of citomni/infrastructure on it. No app configuration is loaded; the
 * connection settings are those of the test server.
 *
 * Rules:
 * - Touch only databases whose names create() generates.
 * - Take the password only from CITOMNI_TEST_PASSWORD; unset means no password. Never write
 *   it to disk, print it or pass it on a command line. Worker processes read it from the
 *   environment they inherit.
 *
 * Typical usage:
 *   $admin = TestDatabase::connect();
 *   $database = TestDatabase::create($admin, 'notices');
 *   try {
 *       $admin->select_db($database);
 *       TestDatabase::import($admin, \file_get_contents($root . '/sql/citomni_demo-provider.sql'));
 *       $app = TestDatabase::app($database, ['demo_provider' => ['notices' => ['max_visible' => 3]]]);
 *   } finally {
 *       TestDatabase::drop($admin, $database);
 *   }
 */
class TestDatabase {

	/** Prefix of every database that a suite of this package creates. */
	private const NAME_PREFIX = 'citomni_demo_provider_';


	/**
	 * Connect to the test server, optionally with a default database.
	 *
	 * Behavior:
	 * - Connects as root to 127.0.0.1:3306 over TCP.
	 *
	 * @param  string  $database  Database to select, or '' for none.
	 * @return \mysqli  Connection with the utf8mb4 charset.
	 */
	public static function connect(string $database = ''): \mysqli {
		// getenv() returns false when the variable is unset; the cast makes that no password.
		$connection = new \mysqli('127.0.0.1', 'root', (string)\getenv('CITOMNI_TEST_PASSWORD'), $database, 3306);
		$connection->set_charset('utf8mb4');

		return $connection;
	}


	/**
	 * Create the run's database.
	 *
	 * Names it NAME_PREFIX, the suite name, _test_ and 12 random hex digits, the only form that
	 * drop() accepts.
	 *
	 * @param  \mysqli  $admin  Connection without a default database.
	 * @param  string  $suite  Suite name: lowercase letters, digits and underscores.
	 * @return string  Name of the new database, generated here.
	 * @throws \InvalidArgumentException  When the suite name has other characters.
	 */
	public static function create(\mysqli $admin, string $suite): string {
		if (\preg_match('/^[a-z0-9_]+$/', $suite) !== 1) {
			throw new \InvalidArgumentException("Invalid suite name for a test database: '{$suite}'");
		}

		$database = self::NAME_PREFIX . $suite . '_test_' . \bin2hex(\random_bytes(6));
		$admin->query('CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

		return $database;
	}


	/**
	 * Drop a database that create() made.
	 *
	 * Refuses any other name, so a wrong argument can never drop a database that no suite
	 * created.
	 *
	 * @param  \mysqli  $admin  Connection to the test server.
	 * @param  string  $database  Name returned by create().
	 * @return void
	 * @throws \LogicException  When the name is not of the form create() generates.
	 */
	public static function drop(\mysqli $admin, string $database): void {
		if (\preg_match('/^' . \preg_quote(self::NAME_PREFIX, '/') . '[a-z0-9_]+_test_[0-9a-f]{12}$/', $database) !== 1) {
			throw new \LogicException('Refusing to drop a database that no suite created: ' . $database);
		}

		$admin->query('DROP DATABASE IF EXISTS `' . $database . '`');
	}


	/**
	 * Run an SQL script with one or more statements and discard every result.
	 *
	 * @param  \mysqli  $connection  Connection with the target database selected.
	 * @param  string  $sql  Script, such as the content of sql/citomni_demo-provider.sql.
	 * @return void
	 */
	public static function import(\mysqli $connection, string $sql): void {
		$connection->multi_query($sql);

		do {
			$result = $connection->store_result();
			if ($result instanceof \mysqli_result) {
				$result->free();
			}
		} while ($connection->more_results() && $connection->next_result());
	}


	/**
	 * Create an App double with the real Db service on the run's database.
	 *
	 * Behavior:
	 * - Registers the services db and secrets. secrets serves only db.password.
	 *
	 * Notes:
	 * - Loads the Db service from vendor/, so "composer install" must have run.
	 *
	 * @param  string  $database  Name returned by create().
	 * @param  array<string,mixed>  $cfg  Further cfg nodes that the code under test reads.
	 * @return App
	 * @throws \RuntimeException  When citomni/infrastructure is missing from vendor/.
	 */
	public static function app(string $database, array $cfg = []): App {
		if (!\class_exists(Db::class)) {
			throw new \RuntimeException('citomni/infrastructure was not found in vendor/. Run "composer install" in the package root first.');
		}

		$app = new App(['db' => [
			'host'    => '127.0.0.1',
			'user'    => 'root',
			'name'    => $database,
			'port'    => 3306,
			'charset' => 'utf8mb4',
		]] + $cfg);

		// Serves the secrets lookup from memory. Assumes that Db asks it for db.password,
		// its default password secret.
		$app->set('secrets', new class((string)\getenv('CITOMNI_TEST_PASSWORD')) {

			/**
			 * Keep the password in memory only.
			 *
			 * @param  string  $password  Database password; '' for none.
			 */
			public function __construct(private readonly string $password) {
			}

			/**
			 * Return the secret stored under the key.
			 *
			 * @param  string  $key  Secret key; only db.password exists.
			 * @return string
			 * @throws \OutOfBoundsException  When the key is not db.password.
			 */
			public function get(string $key): string {
				return $key === 'db.password' ? $this->password : throw new \OutOfBoundsException("Unknown secret: '{$key}'");
			}

			/**
			 * Keep the password out of var_dump() and print_r() output.
			 *
			 * @return array{}
			 */
			public function __debugInfo(): array {
				return [];
			}

		});

		$app->set('db', new Db($app));

		return $app;
	}

}
