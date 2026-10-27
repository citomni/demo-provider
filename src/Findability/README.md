# Findability

A findability provider shows citomni/findability the content that a package owns: its pages in the sitemaps, its pages as targets in the menu editor, its tagged content as cards on the tag pages, and its content as documents in the site search. A package ships providers for the content it owns. The package keeps its data, URLs and publishing rules; findability keeps only what its own features need.

## Setup

A package with providers works without citomni/findability, so it lists the package in `suggest` and `require-dev` of its `composer.json`, not in `require`. Its Registry names each provider only as a `::class` string, so PHP loads the class, and the interface with it, only when findability uses it. Nothing may load every class of `vendor/` up front, such as a preload script.

The app that installs both lists `\CitOmni\Findability\Boot\Registry::class` in `config/providers.php` before the package's Registry, and imports `vendor/citomni/findability/sql/citomni_findability.sql`.

## Contract

| Interface in `CitOmni\Findability\Contract` | Registered in | Keyed by | Findability asks it for |
|---|---|---|---|
| `SitemapSource` | `findability.sitemaps` in `CFG_HTTP` | Source name | The public URLs, counted and in windows |
| `MenuTargetSource` | `findability.menu_targets` in `CFG_HTTP` | Source type | The pages that an editor searches for in the menu editor |
| `CardSource` | `findability.cards` in `CFG_HTTP` | Source type | The cards of tagged content on the tag pages |
| `SearchSource` | `findability.search_sources` in `CFG_COMMON` | Source type | The documents of the site search |

- `class ArticleSearch implements \CitOmni\Findability\Contract\SearchSource` in `src/Findability/`. The name is the content and the role: `ArticleSitemap`, `ArticleTargets`, `ArticleCards`, `ArticleSearch`.
- The constructor takes the App: Findability creates a new instance with `new $class($app)` every time it uses the provider. Keep no state between calls.
- A source type names the content, such as `vendor_package.article`: words of lowercase letters, digits and underscores, each starting with a letter, joined by dots, at most 64 bytes. It starts with the package's cfg root, so it never collides with another package's. Ids are ints of at least 1. A source name of a sitemap is a lowercase letter followed by lowercase letters and digits.
- Each source type gets a label in `findability.types` of `CFG_HTTP`, which names the content on findability's pages.
- The PHPDoc of each interface in citomni/findability is the contract: the shapes, the limits and the rules for paths. Read it before writing the provider.

## Rules

- Read only: no writes, no output and no locking reads. Findability calls a provider inside its own requests and inside the transactions that save content.
- Show only what every visitor may see now, by the same rules as the content's own page. The provider decides on every call; findability never decides access.
- Thin: The SQL stays in the package's Repository. The provider picks the visible rows and shapes them for findability.
- Leave out content that is missing or hidden. An exception aborts the request, or the save that indexes the content, so throw only for a real failure.

## Keep findability's data up to date

Two features store data about the content: the tags of a resource and its document in the search index. The package updates both from the Operation that saves or deletes the content, inside the same transaction, so findability's data commits or rolls back with it:

```php
$this->app->db->transaction(function () use ($articleId, $values, $termIds): void {
	$articles = new ArticleRepository($this->app);
	$articles->update($articleId, $values);  // Locks the article's row.

	if ($this->app->hasPackage('citomni/findability')) {
		$this->app->tags->set('vendor_package.article', $articleId, $termIds);
		$this->app->searchIndex->changed('vendor_package.article', [$articleId]);
	}
}, \CitOmni\Infrastructure\Enum\TransactionIsolation::ReadCommitted);
```

- Run the transaction at READ COMMITTED, and lock the content's own row before the calls, such as by the UPDATE that saves it: Then findability reads what other transactions committed, also those it waited for, and two saves of the content do not interleave. Under REPEATABLE READ, findability reads the snapshot of the transaction's first read and can miss what a rebuild of the search index stored meanwhile; the README of citomni/findability has the details.
- A deletion calls `$this->app->tags->forget()` and `$this->app->searchIndex->removed()` in the transaction that deletes the content.
- `hasPackage()` keeps the package working without findability, where the services do not exist.

## Registration

```php
// src/Boot/Registry.php
public const array CFG_COMMON = [
	'findability' => [
		'search_sources' => [
			'vendor_package.article' => \Vendor\Package\Findability\ArticleSearch::class,  // Published articles, in the site search.
		],
	],
];

public const array CFG_HTTP = [
	'findability' => [
		'sitemaps' => [
			'articles' => \Vendor\Package\Findability\ArticleSitemap::class,  // Published articles.
		],
		'cards' => [
			'vendor_package.article' => \Vendor\Package\Findability\ArticleCards::class,  // Published articles, on the tag pages.
		],
		'types' => [
			'vendor_package.article' => [
				'label' => 'Articles',  // Name of the type on findability's pages.
			],
		],
	],
];
```

The search sources go in `CFG_COMMON`, because content is also saved and indexed in CLI, such as by an import. citomni/findability ships every provider map as an empty map, and an empty array replaces the map before it. The package therefore comes after citomni/findability in the app's `config/providers.php`; its README says so.

## Pattern

Placeholder names; replace them with the real ones.

```php
<?php
declare(strict_types=1);
/*
 * License header: see src/README.md.
 */

namespace Vendor\Package\Findability;

use CitOmni\Findability\Contract\SearchSource;
use CitOmni\Kernel\App;
use Vendor\Package\Repository\ArticleRepository;


/**
 * ArticleSearch: Search source of the package's articles, for citomni/findability.
 *
 * Turns the published articles into the documents of the site search. Which articles are
 * published belongs to ArticleRepository; how the index stores and matches the documents
 * belongs to citomni/findability.
 *
 * Rules:
 * - Return a document only for an article that its own page shows.
 */
class ArticleSearch implements SearchSource {

	/**
	 * Keep the app.
	 *
	 * @param  App  $app
	 */
	public function __construct(private readonly App $app) {
	}


	/**
	 * Return the documents of the published articles among the ids.
	 *
	 * @param  list<int>  $ids
	 * @return array<int,array{path:string,title:string,keywords:string,text:string,publish_at:int|null,expire_at:int|null}>  By id.
	 * @see \CitOmni\Findability\Contract\SearchSource::documents()  The contract.
	 */
	public function documents(array $ids): array {
		return self::documentsOf((new ArticleRepository($this->app))->listByIds($ids));
	}


	/**
	 * Return the documents of the published articles among the next articles above an id.
	 *
	 * @param  int  $after
	 * @param  int  $limit
	 * @return array{documents:array<int,array{path:string,title:string,keywords:string,text:string,publish_at:int|null,expire_at:int|null}>,last:int|null}
	 * @see \CitOmni\Findability\Contract\SearchSource::documentsAfter()  The contract.
	 */
	public function documentsAfter(int $after, int $limit): array {
		// Published or not, so the answer covers every id that it read.
		$rows = (new ArticleRepository($this->app))->listAfter($after, $limit);

		return [
			'documents' => self::documentsOf($rows),
			'last'      => \count($rows) === $limit ? (int)\end($rows)['id'] : null,
		];
	}


	/**
	 * Build the documents of the published articles among some rows.
	 *
	 * @param  list<array{id:int,slug:string,title:string,teaser:string,body:string,status:string}>  $rows
	 * @return array<int,array{path:string,title:string,keywords:string,text:string,publish_at:int|null,expire_at:int|null}>  By id.
	 */
	private static function documentsOf(array $rows): array {
		$documents = [];

		foreach ($rows as $article) {
			if ($article['status'] !== 'published') {
				continue;
			}

			$documents[$article['id']] = [
				'path'       => '/articles/' . $article['slug'] . '-a' . $article['id'] . '.html',
				'title'      => $article['title'],
				'keywords'   => $article['teaser'],
				'text'       => $article['body'],
				'publish_at' => null,
				'expire_at'  => null,
			];
		}

		return $documents;
	}


}
```

## Example

This package has no findability provider. The README of [citomni/findability](https://github.com/citomni/findability) shows one for each interface.
