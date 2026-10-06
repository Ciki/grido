<?php

/**
 * Test: Doctrine.
 *
 * @author     Petr Bugyík
 * @package    Grido\Tests
 */

namespace Grido\Tests;

use Grido\Components\Exports\CsvExport;
use Tester\Assert;
use Grido\Grid;
use Grido\Components\Filters\Condition;

require_once __DIR__ . '/TestCase.php';

if (!class_exists(\Doctrine\ORM\EntityManager::class)) {
    \Tester\Environment::skip('Doctrine ORM is not installed (not a dev dependency).');
}

require_once __DIR__ . '/files/doctrine/entities/Country.php';
require_once __DIR__ . '/files/doctrine/entities/User.php';

class DoctrineTest extends DataSourceTestCase
{
    function setUp()
    {
        Helper::grid(function(Grid $grid, TestPresenter $presenter) {
            $entityManager = $presenter->context->getByType('Doctrine\ORM\EntityManager');
            $repository = $entityManager->getRepository('Grido\Tests\Entities\User');
            $model = new \Grido\DataSources\Doctrine(
                $repository->createQueryBuilder('a') // We need to create query builder with inner join.
                    ->addSelect('c')                 // This will produce less SQL queries with prefetch.
                    ->leftJoin('a.country', 'c'),
                ['country' => 'c.title']);      // Map country column to the title of the Country entity

            $grid->setModel($model);
            $grid->setDefaultPerPage(3);

            $grid->addColumnText('firstname', 'Firstname')
                ->setEditable([$this, 'editableCallbackTest'])
                ->setSortable();
            $grid->addColumnText('surname', 'Surname');
            $grid->addColumnText('gender', 'Gender');
            $grid->addColumnText('phone', 'Phone')
                ->setColumn('telephonenumber')
                ->setFilterText();

            $grid->addFilterText('name', 'Name')
                ->setColumn('surname')
                ->setColumn('firstname', Condition::OPERATOR_AND)
                ->setSuggestion('firstname');

            $grid->addColumnText('country', 'Country')
                ->setSortable()
                ->setFilterText()
                    ->setSuggestion(function($item) {
                        return $item['c_title'];
                    });

            $grid->addFilterCheck('male', 'Only male')
                ->setCondition([
                    TRUE => ['gender', '= ?', 'male']
                ]);

            $grid->addFilterCheck('tall', 'Only tall')
                ->setWhere(function($value, \Doctrine\ORM\QueryBuilder $qb) {
                    Assert::true($value);
                    $qb->andWhere("a.centimeters >= :height")->setParameter('height', 180);
                });

            $grid->addExport(new CsvExport(null, null, ['encoding' => CsvExport::ENCODING_UTF8, 'delimiter' => ',']), 'csv');

        })->run();
    }
}

run(__FILE__);
