<?php
namespace Readerself\CoreBundle\Repository;

use Readerself\CoreBundle\Repository\AbstractRepository;

class ActionItemRepository extends AbstractRepository
{
    public function getOne($parameters = []) {
        $em = $this->getEntityManager();

        $query = $em->createQueryBuilder();
        $query->addSelect('act_itm');
        $query->from('ReaderselfCoreBundle:ActionItem', 'act_itm');

        if(isset($parameters['id']) == 1) {
            $query->andWhere('act_itm.id = :id');
            $query->setParameter(':id', $parameters['id']);
        }

        if(isset($parameters['action']) == 1) {
            $query->andWhere('act_itm.action = :action');
            $query->setParameter(':action', $parameters['action']);
        }

        if(isset($parameters['item']) == 1) {
            $query->andWhere('act_itm.item = :item');
            $query->setParameter(':item', $parameters['item']);
        }

        $getQuery = $query->getQuery();
        $getQuery->setMaxResults(1);

        /*$cacheDriver = new \Doctrine\Common\Cache\ApcuCache();
        $cacheDriver->setNamespace('readerself.action_item_member.');
        $getQuery->setResultCacheDriver($cacheDriver);
        $getQuery->setResultCacheLifetime(86400);*/

        return $getQuery->getOneOrNullResult();
    }

    public function getList($parameters = []) {
        $em = $this->getEntityManager();

        $query = $em->createQueryBuilder();
        $query->addSelect('itm', 'fed', 'aut');
        $query->from('ReaderselfCoreBundle:Item', 'itm');
        $query->leftJoin('itm.feed', 'fed');
        $query->leftJoin('itm.author', 'aut');

        if(isset($parameters['feed']) == 1) {
            $query->andWhere('itm.feed = :feed');
            $query->setParameter(':feed', $parameters['feed']);
        }

        if(isset($parameters['member']) == 1) {
            $query->leftJoin('fed.subscriptions', 'sub');
            $query->andWhere('sub.member = :action');
            $query->setParameter(':member', $parameters['member']);
        }

        $query->addOrderBy('itm.date', 'DESC');
        $query->groupBy('itm.id');
        $query->setMaxResults(20);

        $getQuery = $query->getQuery();

        /*$cacheDriver = new \Doctrine\Common\Cache\ApcuCache();
        $cacheDriver->setNamespace('readerself.action_item_member.');
        $getQuery->setResultCacheDriver($cacheDriver);
        $getQuery->setResultCacheLifetime(86400);*/

        return $getQuery->getResult();
    }
}