<?php
namespace Fontai\Propel;

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Propel;


class Navigation
{
  public static function getNavigation(
    ModelCriteria $query,
    ActiveRecordInterface $object
  )
  {
    $result = [];

    //Only for saved entity
    if ($object->isNew())
    {
      return $result;
    }

    $query
    ->clearSelectColumns()
    ->select(['Id']);

    $params = [];
    $baseSql = $query->createSelectSql($params);
    
    $databaseName = constant(sprintf('%s::DATABASE_NAME', $object::TABLE_MAP));
    $con = Propel::getReadConnection($databaseName);

    $stmt = $con->prepare(sprintf('CREATE TEMPORARY TABLE `_sort_data` (%s)', $baseSql));
    Propel::getAdapter($databaseName)->bindValues($stmt, $params, Propel::getDatabaseMap($databaseName));
    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $con->prepare(
      'CREATE TEMPORARY TABLE `_sort`
      (
        SELECT @i := @i + 1 AS `Position`, `_sort_data`.`Id`
        FROM `_sort_data`
        INNER JOIN (SELECT @i := 0) AS `_position`
      )'
    );

    $stmt->execute();
    $stmt->closeCursor();

    $stmt = $con->prepare(
      'SELECT `Position`
      FROM `_sort`
      WHERE `Id` = :id'
    );
    $stmt->bindValue(':id', $object->getId(), \PDO::PARAM_INT);
    $stmt->execute();

    if ($row = $stmt->fetch(\PDO::FETCH_NUM))
    {
      $stmt = $con->prepare(
        'SELECT CASE `Position`
          WHEN :prev THEN \'prev\'
          WHEN :next THEN \'next\'
          ELSE NULL
        END AS `Type`,
        `Id`
        FROM `_sort`
        HAVING `Type` IS NOT NULL'
      );

      $stmt->bindValue(':prev', $row[0] - 1, \PDO::PARAM_INT);
      $stmt->bindValue(':next', $row[0] + 1, \PDO::PARAM_INT);
      $stmt->execute();

      while ($row = $stmt->fetch(\PDO::FETCH_NUM))
      {
        $result[$row[0]] = $row[1];
      }

      $stmt->closeCursor();
    }
    
    $stmt->closeCursor();

    return $result;
  }
}