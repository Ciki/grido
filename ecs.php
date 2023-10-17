<?php

declare(strict_types=1);

use Ecs\Fixer\AlignCommentsFixer;
use Ecs\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use Symplify\CodingStandard\Fixer\LineLength\LineLengthFixer;
// use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

require_once __DIR__ . '/libs/Ecs/Fixer/ClassNotation/ClassAttributesSeparationFixer.php';
require_once __DIR__ . '/libs/Ecs/Fixer/AlignCommentsFixer.php';

// https://github.com/symplify/easy-coding-standard#configuration
return static function (ECSConfig $ecsConfig): void {
	$ecsConfig->parallel();

	// alternative to CLI arguments, easier to maintain and extend
	$ecsConfig->paths([
		__DIR__ . '/src',
		// __DIR__ . '/tests',
	]);

	// configure cache paths & namespace - useful for Gitlab CI caching, where getcwd() produces always different path
	// [default: sys_get_temp_dir() . '/_changed_files_detector_tests']
	$ecsConfig->cacheDirectory(__DIR__ . '/temp/.ecs_cache');

	// indent and tabs/spaces
	// [default: spaces]
	$ecsConfig->indentation('tab');

	// [default: PHP_EOL]; other options: "\n"
	// $ecsConfig->lineEnding("\r\n");

	// custom rewrites of sets
	$ecsConfig->skip([
		// keep single-line doc-comments intact -> do not convert to block phpDocs
		PhpdocLineSpanFixer::class,

		// do not use till https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/6981 is solved
		LineLengthFixer::class,

		// disabled as this breaks layout more often than helps
		Symplify\CodingStandard\Fixer\Spacing\MethodChainingNewlineFixer::class,

		// skip for files where it does not behave as expected
		// PhpCsFixer\Fixer\Basic\BracesFixer::class => [
		// 	__DIR__ . '/app/templates/maintenance.php',
		// ],
		// PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer::class => [
		// 	__DIR__ . '/app/templates/maintenance.php',
		// ],

		// disable from preset
		PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer::class,
		PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer::class,
		// PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer::class,

	]);

	// A. full sets
	$ecsConfig->sets([
		SetList::ARRAY,
		SetList::CLEAN_CODE,
		SetList::COMMENTS,
		// SetList::COMMON, // only alias for other SetList from common/ directory
		SetList::CONTROL_STRUCTURES,
		SetList::DOCBLOCK,
		SetList::NAMESPACES,
		SetList::PHPUNIT,
		SetList::PSR_12,
		SetList::SPACES,
		SetList::STRICT,
		SetList::SYMPLIFY,
	]);

	// $ecsConfig->rule(NoUnusedImportsFixer::class);

	// override default from SetList::SPACES
	$ecsConfig->ruleWithConfiguration(ClassAttributesSeparationFixer::class, [
		'elements' => [
			// 'const' => ClassAttributesSeparationFixer::SPACING_ONE,
			// 'const' => ClassAttributesSeparationFixer::SPACING_ONLY_IF_META,
			'const' => 'only_if_meta',
			// 'trait_import' => ClassAttributesSeparationFixer::SPACING_NONE,
			'property' => ClassAttributesSeparationFixer::SPACING_ONE,
			'method' => ClassAttributesSeparationFixer::SPACING_TWO,
		],
	]);
	// override default from SetList::SYMPLIFY
	$ecsConfig->ruleWithConfiguration(GeneralPhpdocAnnotationRemoveFixer::class, [
		'annotations' => [/*'throws', */'author', 'package', 'group', 'covers', 'category'],
	]);
	// only for migration of old scripts developed in NetBeans with `//` at the beginning of the line
	$ecsConfig->rule(AlignCommentsFixer::class);
};
