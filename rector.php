<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\PhpVersion;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Privatization\Rector\Class_\FinalizeClassesWithoutChildrenRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\FunctionLike\ParamTypeDeclarationRector;
use Rector\TypeDeclaration\Rector\Property\PropertyTypeDeclarationRector;
// use RectorNette\Set\NetteSetList;

return static function (RectorConfig $rectorConfig): void {
	$rectorConfig->paths([
		__DIR__ . '/src',
		// __DIR__ . '/tests',
	]);

	$rectorConfig->phpVersion(PhpVersion::PHP_81);
	// $rectorConfig->phpVersion(PhpVersion::PHP_82);
	$rectorConfig->importNames();
	$rectorConfig->indent("\t", 1);

	// Ensure file system caching is used instead of in-memory.
	$rectorConfig->cacheClass(FileCacheStorage::class);

	// Specify a path that works locally as well as on CI job runners.
	$rectorConfig->cacheDirectory('./temp/cache/rector');

	// register a single rule
	// $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
	$rectorConfig->rule(FinalizeClassesWithoutChildrenRector::class);

	// convert phpdoc types to valid php types .. available only in rector < 0.15
	// see https://github.com/rectorphp/rector/issues/7951
	/* $rectorConfig->rule(ParamTypeDeclarationRector::class);
	$rectorConfig->rule(PropertyTypeDeclarationRector::class);
	$rectorConfig->ruleWithConfiguration(Rector\Php74\Rector\Property\TypedPropertyRector::class, [
		Rector\Php74\Rector\Property\TypedPropertyRector::INLINE_PUBLIC => true,
	]); */

	// https://getrector.com/documentation/ignoring-rules-or-paths
	$rectorConfig->skip([
		// we'd like promotion ONLY if all properties are declared in constructor.. for now we rather skip this rule
		// ClassPropertyAssignToConstructorPromotionRector::class,

		// RemoveExtraParametersRector::class,

		// we want to keep parent::__construct() for future-proof change
		RemoveParentCallWithoutParentRector::class,

		// we use phpstan for type safety checks, no need to explicitly cast to string
		NullToStrictStringFuncCallArgRector::class,

		__DIR__ . '/libs/Ecs',
	]);

	// define sets of rules
	$rectorConfig->sets([
		// for Symfony & Laravel only https://getrector.com/blog/how-to-instantly-refactor-symfony-action-injects-to-constructor-injection
		// // SetList::ACTION_INJECTION_TO_CONSTRUCTOR_INJECTION,

		// SetList::CODE_QUALITY, // not all of these we want to apply, run with `--dry-run` first
		// // SetList::CODING_STYLE,
		// SetList::DEAD_CODE, // not all of these we want to apply, run with `--dry-run` first
		// SetList::EARLY_RETURN, // not all of these we want to apply, run with `--dry-run` first
		// // SetList::GMAGICK_TO_IMAGICK,
		// // SetList::INSTANCEOF,
		// // SetList::MYSQL_TO_MYSQLI,
		// // SetList::NAMING,

		// https://github.com/efabrica-team/rector-nette/
		// https://github.com/efabrica-team/rector-nette/blob/main/docs/rector_rules_overview.md
		// NetteSetList::ANNOTATIONS_TO_ATTRIBUTES,
		// NetteSetList::NETTE_24,
		// NetteSetList::NETTE_30,
		// NetteSetList::NETTE_31,
		// NetteSetList::NETTE_CODE_QUALITY,
		// NetteSetList::NETTE_REMOVE_INJECT,

		// // SetList::PRIVATIZATION,
		// // SetList::PSR_4,
		SetList::TYPE_DECLARATION,
		LevelSetList::UP_TO_PHP_81,
		// LevelSetList::UP_TO_PHP_82,
	]);

	// $rectorConfig->ruleWithConfiguration(RenameClassRector::class, [
	//     'App\SomeOldClass' => 'App\SomeNewClass',
	// ]);
};
