<?php
namespace Readerself\CoreBundle\Repository;

use Readerself\CoreBundle\Repository\AbstractRepository;

class AuthorRepository extends AbstractRepository
{
    public function getOne($parameters = []) {
        $em = $this->getEntityManager();

        $query = $em->createQueryBuilder();
        $query->addSelect('aut');
        $query->from('ReaderselfCoreBundle:Author', 'aut');

        if(isset($parameters['id']) == 1) {
            $query->andWhere('aut.id = :id');
            $query->setParameter(':id', $parameters['id']);
        }

        if(isset($parameters['title']) == 1) {
            $query->andWhere('aut.title = :title');
            $query->setParameter(':title', $parameters['title']);
        }

        $getQuery = $query->getQuery();
        $getQuery->setMaxResults(1);

        if($cacheDriver = $this->cacheDriver()) {
            $cacheDriver->setNamespace('readerself.author.');
            $getQuery->setResultCacheDriver($cacheDriver);
            $getQuery->setResultCacheLifetime(86400);
        }

        return $getQuery->getOneOrNullResult();
    }

    public function getList($parameters = []) {
        $em = $this->getEntityManager();

        if(isset($parameters['trendy']) == 1 && $parameters['trendy']) {
            if(isset($parameters['member'])) {
                $exclude = 'AND aut.id NOT IN( SELECT author_id FROM action_author WHERE member_id = :member_id AND action_id = :action_id )';
            } else {
                $exclude = '';
            }

$sql = <<<SQL
SELECT LOWER(aut.title) AS ref, aut.id AS id, COUNT(DISTINCT(itm.id)) AS count
FROM item AS itm
LEFT JOIN author AS aut ON aut.id = itm.author_id
WHERE itm.date >= :date_ref AND aut.title != '' $exclude
GROUP BY ref
ORDER BY count DESC
LIMIT 0,100
SQL;

            $date_ref = date('Y-m-d H:i:s', time() - 3600 * 24 * 30);

            $stmt = $em->getConnection()->prepare($sql);
            $stmt->bindValue('date_ref', $date_ref);
            if(isset($parameters['member'])) {
                $stmt->bindValue('member_id', $parameters['member']->getId());
                $stmt->bindValue('action_id', 5);
            }
            $stmt->execute();
            $results = $stmt->fetchAll();

            return $results;
        }

        $query = $em->createQueryBuilder();
        $query->addSelect('aut.id');
        $query->from('ReaderselfCoreBundle:Author', 'aut');

        if(isset($parameters['excluded']) == 1 && $parameters['excluded']) {
            $query->andWhere('aut.id IN (SELECT IDENTITY(excluded.author) FROM ReaderselfCoreBundle:ActionAuthor AS excluded WHERE excluded.member = :member AND excluded.action = 5)');
            $query->setParameter(':member', $parameters['member']);
        }

        if(isset($parameters['feed']) == 1) {
            $query->andWhere('aut.id IN (SELECT IDENTITY(item.author) FROM ReaderselfCoreBundle:Item AS item WHERE item.feed = :feed)');
            $query->setParameter(':feed', $parameters['feed']);
        }

        if(isset($parameters['days']) == 1) {
            $query->andWhere('aut.dateCreated > :date');
            $query->setParameter(':date', new \DateTime('-'.$parameters['days'].' days'));
        }

        $query->addOrderBy($parameters['sortField'], $parameters['sortDirection']);
        $query->groupBy('aut.id');

        $getQuery = $query->getQuery();
        return $getQuery;
    }
}
