<?php

declare(strict_types=1);

namespace TYPO3\CMS\Extbase\Persistence {
    interface QueryInterface
    {
        const ORDER_DESCENDING = 'DESC';
    }

    class Repository
    {
        protected function createQuery()
        {
            return new \TestSupport\Query();
        }
    }
}

namespace TYPO3\CMS\Core\Database {
    class ConnectionPool
    {
        public function getQueryBuilderForTable($table)
        {
            return \TestSupport\Registry::$queryBuilder = new \TestSupport\QueryBuilder();
        }
    }
}

namespace TYPO3\CMS\Core\Utility {
    class GeneralUtility
    {
        public static function makeInstance($class)
        {
            return new $class();
        }

        public static function trimExplode($delimiter, $value, $removeEmptyValues = false)
        {
            $values = array_map('trim', explode($delimiter, $value));

            return $removeEmptyValues
                ? array_values(array_filter($values, static function ($item) { return $item !== ''; }))
                : $values;
        }

        public static function _GET($key)
        {
            return [];
        }
    }
}

namespace Ameos\AmeosFilemanager\Configuration {
    class Configuration
    {
        const DIRECTION_ARGUMENT_KEY = 'direction';
    }
}

namespace Ameos\AmeosFilemanager\Utility {
    class FilemanagerUtility
    {
        public static function fileContentSearchEnabled()
        {
            return false;
        }
    }
}

namespace TestSupport {
    class Registry
    {
        public static $queryBuilder;
    }

    class Restrictions
    {
        public function removeAll()
        {
        }
    }

    class Expression
    {
        public function like($field, $value)
        {
            return $field . ' LIKE ' . $value;
        }

        public function eq($field, $value)
        {
            return $field . ' = ' . $value;
        }

        public function in($field, $values)
        {
            return $field . ' IN (' . implode(', ', $values) . ')';
        }
    }

    class QueryBuilder
    {
        public $quotedValues = [];
        public $orWhere = [];
        public $andWhere = [];

        public function getRestrictions()
        {
            return new Restrictions();
        }

        public function select()
        {
            return $this;
        }

        public function from()
        {
            return $this;
        }

        public function join()
        {
            return $this;
        }

        public function leftJoin()
        {
            return $this;
        }

        public function expr()
        {
            return new Expression();
        }

        public function escapeLikeWildcards($value)
        {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
        }

        public function quote($value)
        {
            $this->quotedValues[] = $value;

            return "'" . str_replace("'", "''", $value) . "'";
        }

        public function orWhere()
        {
            $this->orWhere = func_get_args();

            return $this;
        }

        public function andWhere()
        {
            $this->andWhere[] = func_get_args();

            return $this;
        }

        public function orderBy()
        {
            return $this;
        }

        public function getSQL()
        {
            return '';
        }
    }

    class Query
    {
        public function statement($sql)
        {
            return $this;
        }

        public function execute()
        {
            return [];
        }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/Classes/Domain/Repository/FileRepository.php';

    class TestableFileRepository extends \Ameos\AmeosFilemanager\Domain\Repository\FileRepository
    {
        protected function getFilesIdentifiersRecursively($folders, $recursiveLimit = 0, $currentRecursive = 1)
        {
            return [42, 43];
        }
    }

    function assertSameValue($expected, $actual, $message)
    {
        if ($expected !== $actual) {
            throw new \RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
        }
    }

    function search($keyword, $rootFolder = null)
    {
        $repository = new TestableFileRepository();
        $repository->findBySearchCriterias(['keyword' => $keyword], $rootFolder);

        return \TestSupport\Registry::$queryBuilder;
    }

    $quotedInput = search("O'Reilly");
    assertSameValue(["%O'Reilly%"], $quotedInput->quotedValues, 'Quotes must be passed through QueryBuilder::quote().');
    assertSameValue("sys_file_metadata.title LIKE '%O''Reilly%'", $quotedInput->orWhere[0], 'Quotes must remain escaped in LIKE clauses.');

    $sqlLikeInput = search("' OR 1=1 --");
    assertSameValue(["%'%", '%OR%', '%1=1%', '%--%'], $sqlLikeInput->quotedValues, 'SQL-like input must be split and quoted as data.');
    assertSameValue("sys_file_metadata.title LIKE '%''%'", $sqlLikeInput->orWhere[0], 'SQL-like input must not terminate the LIKE literal.');

    $wildcardInput = search('100%_');
    assertSameValue(['%100\\%\\_%'], $wildcardInput->quotedValues, 'LIKE wildcards must be escaped before quoting.');

    $whitespaceInput = search('   ');
    assertSameValue([['1 = 0']], $whitespaceInput->andWhere, 'Whitespace-only searches must not return every file.');

    $folderRestrictedInput = search('report', 99);
    assertSameValue([['sys_file.uid IN (42, 43)']], $folderRestrictedInput->andWhere, 'Searches must remain restricted to the selected folder tree.');

    echo "Search security regression tests passed.\n";
}
