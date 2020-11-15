<?php
namespace Readerself\CoreBundle\Manager;

use Readerself\CoreBundle\Manager\AbstractManager;
use Readerself\CoreBundle\Entity\Item;
use Readerself\CoreBundle\Event\ItemEvent;

class ItemManager extends AbstractManager
{
    public $enclosureManager;

    public function __construct(
        EnclosureManager $enclosureManager
    ) {
        $this->enclosureManager = $enclosureManager;
    }

    public function getOne($paremeters = [])
    {
        return $this->em->getRepository('ReaderselfCoreBundle:Item')->getOne($paremeters);
    }

    public function getList($parameters = [])
    {
        return $this->em->getRepository('ReaderselfCoreBundle:Item')->getList($parameters);
    }

    public function init()
    {
        return new Item();
    }

    public function persist($data)
    {
        if($data->getDateCreated() == null) {
            $mode = 'insert';
            $data->setDateCreated(new \Datetime());
        } else {
            $mode = 'update';
        }
        $data->setDateModified(new \Datetime());

        $this->em->persist($data);
        $this->em->flush();

        $event = new ItemEvent($data, $mode);
        $this->eventDispatcher->dispatch('Item.after_persist', $event);

        $this->clearCache();

        return $data->getId();
    }

    public function remove($data)
    {
        $event = new ItemEvent($data, 'delete');
        $this->eventDispatcher->dispatch('Item.before_remove', $event);

        $this->em->remove($data);
        $this->em->flush();

        $this->clearCache();
    }

    public function readAll($parameters = [])
    {
        foreach($this->em->getRepository('ReaderselfCoreBundle:Item')->getList($parameters)->getResult() as $result) {
            $sql = 'SELECT id FROM action_item WHERE member_id = :member_id AND item_id = :item_id AND action_id = :action_id';
            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue('member_id', $parameters['member']->getId());
            $stmt->bindValue('item_id', $result['id']);
            $stmt->bindValue('action_id', 1);
            $stmt->execute();
            $test = $stmt->fetch();

            if($test) {
            } else {
                $insertActionItem = [
                    'member_id' => $parameters['member']->getId(),
                    'item_id' => $result['id'],
                    'action_id' => 1,
                    'date_created' => (new \Datetime())->format('Y-m-d H:i:s'),
                ];
                $this->insert('action_item', $insertActionItem);

                $sql = 'DELETE FROM action_item WHERE action_id = :action_id AND item_id = :item_id AND member_id = :member_id';
                $stmt = $this->connection->prepare($sql);
                $stmt->bindValue('action_id', 12);
                $stmt->bindValue('item_id', $result['id']);
                $stmt->bindValue('member_id', $parameters['member']->getId());
                $stmt->execute();
            }
        }
    }
}
