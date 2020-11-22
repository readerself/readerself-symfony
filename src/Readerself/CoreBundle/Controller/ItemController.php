<?php
namespace Readerself\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

use Readerself\CoreBundle\Controller\AbstractController;

class ItemController extends AbstractController
{
    /**
     * Retrieve all items.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     *     parameters={
     *         {"name"="sortField", "dataType"="string", "required"=false, "format"="""title"" or ""date"", default ""score""", "description"=""},
     *         {"name"="sortDirection", "dataType"="string", "required"=false, "format"="""ASC"" or ""DESC"", default ""DESC""", "description"=""},
     *         {"name"="page", "dataType"="integer", "required"=false, "format"="default ""1""", "description"="page number"},
     *         {"name"="perPage", "dataType"="integer", "required"=false, "format"="default ""20""", "description"="items per page"},
     *         {"name"="starred", "dataType"="integer", "required"=false, "format"="1 or 0", "description"="items with action ""star"""},
     *         {"name"="unread", "dataType"="integer", "required"=false, "format"="1 or 0", "description"="items with no action ""read"""},
     *         {"name"="geolocation", "dataType"="integer", "required"=false, "format"="1 or 0", "description"="items with geolocation"},
     *         {"name"="feed", "dataType"="integer", "required"=false, "format"="feed ID", "description"="items by feed"},
     *         {"name"="author", "dataType"="integer", "required"=false, "format"="author ID", "description"="items by author"},
     *         {"name"="category", "dataType"="integer", "required"=false, "format"="category ID", "description"="items by category"},
     *         {"name"="days", "dataType"="integer", "required"=false, "description"="Limit items to X days"},
     *     },
     *     statusCodes={
     *         200="Returned when successful",
     *         403="Returned when the user is not authorized to say hello",
     *         404={
     *           "Returned when the user is not found",
     *           "Returned when something else is not found"
     *         }
     *     }
     * )
     */
    public function indexAction(Request $request)
    {
        $data = [];
        $memberConnected = $this->validateToken($request);

        $parameters = [];
        $parameters['member'] = $memberConnected;

        if($request->query->get('starred')) {
            if(!$memberConnected) {
                return new JsonResponse($data, 403);
            }
            $parameters['starred'] = $request->query->get('starred');
        }

        if($request->query->get('unread')) {
            if(!$memberConnected) {
                return new JsonResponse($data, 403);
            }
            $parameters['unread'] = (bool) $request->query->get('unread');
        }

        if($request->query->get('geolocation')) {
            $parameters['geolocation'] = (bool) $request->query->get('geolocation');
        }

        if($request->query->get('feed')) {
            $parameters['feed'] = (int) $request->query->get('feed');
            $data['entry'] = $this->feedManager->getOne(['id' => (int) $request->query->get('feed')])->toArray();
            $data['entry_entity'] = 'feed';
        }

        if($request->query->get('author')) {
            $parameters['author'] = (int) $request->query->get('author');
            $data['entry'] = $this->authorManager->getOne(['id' => (int) $request->query->get('author')])->toArray();
            $data['entry_entity'] = 'author';
        }

        if($request->query->get('category')) {
            $parameters['category'] = (int) $request->query->get('category');
            $data['entry'] = $this->categoryManager->getOne(['id' => (int) $request->query->get('category')])->toArray();
            $data['entry_entity'] = 'category';
        }

        if($request->query->get('unread')) {
            $page = 1;
        } else {
            $page = $request->query->getInt('page', 1);
        }

        if($request->query->get('days')) {
            $parameters['days'] = (int) $request->query->get('days');
        }

        $fields = ['title' => 'itm.title', 'date' => 'itm.date'];
        if($request->query->get('sortField') && array_key_exists($request->query->get('sortField'), $fields)) {
            $parameters['sortField'] = $fields[$request->query->get('sortField')];
        } else {
            $parameters['sortField'] = 'itm.date';
        }

        $directions = ['ASC', 'DESC'];
        if($request->query->get('sortDirection') && in_array($request->query->get('sortDirection'), $directions)) {
            $parameters['sortDirection'] = $request->query->get('sortDirection');
        } else {
            $parameters['sortDirection'] = 'DESC';
        }

        $paginator= $this->get('knp_paginator');
        $paginator->setDefaultPaginatorOptions(['widgetParameterName' => 'page']);
        $pagination = $paginator->paginate(
            $this->itemManager->getList($parameters),
            $page,
            $request->query->getInt('perPage', 20)
        );

        $data['entries_entity'] = 'item';
        $data['entries_total'] = $pagination->getTotalItemCount();
        $data['entries_pages'] = $pages = $pagination->getPageCount();
        $data['entries_page_current'] = $page;
        $pagePrevious = $page - 1;
        if($pagePrevious >= 1) {
            $data['entries_page_previous'] = $pagePrevious;
        }
        $pageNext = $page + 1;
        if($pageNext <= $pages) {
            $data['entries_page_next'] = $pageNext;
        }

        if($memberConnected) {
            if($request->query->get('unread')) {
                $data['unread'] = $pagination->getTotalItemCount();
            } else {
                $data['unread'] = $this->memberManager->countUnread($memberConnected->getId());
            }
        }

        $data['entries'] = [];

        $index = 0;
        foreach($pagination as $result) {
            $item = $this->itemManager->getOne(['id' => $result['id']]);

            $actions = $this->actionManager->actionItemManager->getList(['member' => $memberConnected, 'item' => $item])->getResult();

            $categories = [];
            foreach($this->categoryManager->itemCategoryManager->getList(['member' => $memberConnected, 'item' => $item])->getResult() as $itemCategory) {
                $categories[] = $itemCategory->toArray();
            }

            $data['entries'][$index] = $item->toArray();
            foreach($actions as $action) {
                $data['entries'][$index][$action->getAction()->getTitle()] = true;
            }
            $data['entries'][$index]['categories'] = $categories;
            $data['entries'][$index]['enclosures'] = $this->itemManager->prepareEnclosures($item, $request);

            $data['entries'][$index]['content'] = $this->itemManager->cleanContent($item->getContent(), 'display');

            $index++;
        }

        return new JsonResponse($data);
    }

    /**
     * Retrieve an item.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     * )
     */
    public function readAction(Request $request, $id)
    {
        $data = [];
        $memberConnected = $this->validateToken($request);

        $item = $this->itemManager->getOne(['id' => $id]);

        if(!$item) {
            return new JsonResponse($data, 404);
        }

        $actions = $this->actionManager->actionItemManager->getList(['member' => $memberConnected, 'item' => $item])->getResult();

        $categories = [];
        foreach($this->categoryManager->itemCategoryManager->getList(['member' => $memberConnected, 'item' => $item])->getResult() as $itemCategory) {
            $categories[] = $itemCategory->toArray();
        }

        $data['entry'] = $item->toArray();
        foreach($actions as $action) {
            $data['entry'][$action->getAction()->getTitle()] = true;
        }
        $data['entry']['categories'] = $categories;
        $data['entry']['enclosures'] = $this->itemManager->prepareEnclosures($item, $request);

        $data['entry']['content'] = $this->itemManager->cleanContent($item->getContent(), 'display');

        $data['entry_entity'] = 'item';

        return new JsonResponse($data);
    }

    /**
     * Delete an item.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     * )
     */
    public function deleteAction(Request $request, $id)
    {
        $data = [];
        if(!$memberConnected = $this->validateToken($request)) {
            return new JsonResponse($data, 403);
        }

        if(!$memberConnected->getAdministrator()) {
            return new JsonResponse($data, 403);
        }

        $item = $this->itemManager->getOne(['id' => $id]);

        if(!$item) {
            return new JsonResponse($data, 404);
        }

        $data['entry'] = $item->toArray();
        $data['entry_entity'] = 'item';

        $this->itemManager->remove($item);

        return new JsonResponse($data);
    }

    /**
     * Mark all items as read.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     *     parameters={
     *         {"name"="starred", "dataType"="integer", "required"=false, "format"="1 or 0", "description"="items with action ""star"""},
     *         {"name"="feed", "dataType"="integer", "required"=false, "format"="feed ID", "description"="items by feed"},
     *         {"name"="author", "dataType"="integer", "required"=false, "format"="author ID", "description"="items by author"},
     *         {"name"="category", "dataType"="integer", "required"=false, "format"="category ID", "description"="items by category"},
     *     },
     * )
     */
    public function markallasreadAction(Request $request)
    {
        $data = [];
        if(!$memberConnected = $this->validateToken($request)) {
            return new JsonResponse($data, 403);
        }

        $parameters = [];
        $parameters['member'] = $memberConnected;

        $parameters['unread'] = (bool) $request->query->get('unread');

        if($request->query->get('starred')) {
            $parameters['starred'] = $request->query->get('starred');
        }

        if($request->query->get('feed')) {
            $parameters['feed'] = (int) $request->query->get('feed');
        }

        if($request->query->get('author')) {
            $parameters['author'] = (int) $request->query->get('author');
        }

        if($request->query->get('category')) {
            $parameters['category'] = (int) $request->query->get('category');
        }

        if($request->query->get('age')) {
            $parameters['age'] = (int) $request->query->get('age');
        }

        $parameters['sortField'] = 'itm.id';

        $parameters['sortDirection'] = 'DESC';

        $this->itemManager->readAll($parameters);

        if($memberConnected) {
            $data['unread'] = $this->memberManager->countUnread($memberConnected->getId());
        }

        return new JsonResponse($data);
    }

    /**
     * Set "read" action / Remove "read" action.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     * )
     */
    public function actionReadAction(Request $request, $id)
    {
        return $this->setAction('read', $request, $id);
    }

    /**
     * Set "star" action / Remove "star" action.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     * )
     */
    public function actionStarAction(Request $request, $id)
    {
        return $this->setAction('star', $request, $id);
    }

    private function setAction($case, Request $request, $id)
    {
        $data = [];
        if(!$memberConnected = $this->validateToken($request)) {
            return new JsonResponse($data, 403);
        }

        $item = $this->itemManager->getOne(['id' => $id]);

        if(!$item) {
            return new JsonResponse($data, 404);
        }

        $action = $this->actionManager->getOne(['title' => $case]);

        if($actionItem = $this->actionManager->actionItemManager->getOne([
            'action' => $action,
            'item' => $item,
            'member' => $memberConnected,
        ])) {
            $this->actionManager->actionItemManager->remove($actionItem);

            $data['action'] = $action->getReverse()->getTitle();
            $data['action_reverse'] = $action->getTitle();

            if($action->getReverse()) {
                if($actionItemReverse = $this->actionManager->actionItemManager->getOne([
                    'action' => $action->getReverse(),
                    'item' => $item,
                    'member' => $memberConnected,
                ])) {
                } else {
                    $actionItemReverse = $this->actionManager->actionItemManager->init();
                    $actionItemReverse->setAction($action->getReverse());
                    $actionItemReverse->setItem($item);
                    $actionItemReverse->setMember($memberConnected);
                    $this->actionManager->actionItemManager->persist($actionItemReverse);
                }
            }
        } else {
            $actionItem = $this->actionManager->actionItemManager->init();
            $actionItem->setAction($action);
            $actionItem->setItem($item);
            $actionItem->setMember($memberConnected);
            $this->actionManager->actionItemManager->persist($actionItem);

            $data['action'] = $action->getTitle();
            $data['action_reverse'] = $action->getReverse()->getTitle();

            if($action->getReverse()) {
                if($actionItemReverse = $this->actionManager->actionItemManager->getOne([
                    'action' => $action->getReverse(),
                    'item' => $item,
                    'member' => $memberConnected,
                ])) {
                    $this->actionManager->actionItemManager->remove($actionItemReverse);
                }
            }
        }

        $data['entry'] = $item->toArray();
        $data['entry_entity'] = 'item';

        if($case == 'read') {
            $data['unread'] = $this->memberManager->countUnread($memberConnected->getId());
        }

        return new JsonResponse($data);
    }

    /**
     * Send item by email.
     *
     * @ApiDoc(
     *     section="Item",
     *     headers={
     *         {"name"="X-CONNECTION-TOKEN","required"=true},
     *     },
     *     parameters={
     *         {"name"="subject", "dataType"="string", "required"=true, "format"="default ""item title""", "description"=""},
     *         {"name"="recipient", "dataType"="string", "required"=true, "format"="email", "description"=""},
     *         {"name"="message", "dataType"="string", "required"=false, "format"="", "description"=""},
     *         {"name"="replyTo", "dataType"="string", "required"=false, "format"="email", "description"=""},
     *     },
     * )
     */
    public function actionEmailAction(Request $request, $id)
    {
        $data = [];
        if(!$memberConnected = $this->validateToken($request)) {
            return new JsonResponse($data, 403);
        }

        $item = $this->itemManager->getOne(['id' => $id]);

        if(!$item) {
            return new JsonResponse($data, 404);
        }

        $action = $this->actionManager->getOne(['title' => 'email']);

        return new JsonResponse($data);
    }
}
