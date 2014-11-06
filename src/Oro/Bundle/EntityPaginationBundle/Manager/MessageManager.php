<?php

namespace Oro\Bundle\EntityPaginationBundle\Manager;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Translation\TranslatorInterface;

use Doctrine\Common\Util\ClassUtils;

use Oro\Bundle\EntityPaginationBundle\Navigation\EntityPaginationNavigation;
use Oro\Bundle\EntityPaginationBundle\Storage\EntityPaginationStorage;

class MessageManager
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var EntityPaginationNavigation
     */
    protected $navigation;

    /**
     * @var EntityPaginationStorage
     */
    protected $storage;

    /**
     * @param Session $session
     * @param TranslatorInterface $translator
     * @param EntityPaginationNavigation $navigation
     * @param EntityPaginationStorage $storage
     */
    public function __construct(
        Session $session,
        TranslatorInterface $translator,
        EntityPaginationNavigation $navigation,
        EntityPaginationStorage $storage
    ) {
        $this->session = $session;
        $this->translator = $translator;
        $this->navigation = $navigation;
        $this->storage = $storage;
    }

    /**
     * @param string $type
     * @param string $message
     */
    public function addFlashMessage($type, $message)
    {
        $this->session->getFlashBag()->add($type, $message);
    }

    /**
     * @param object $entity
     * @param string $scope
     * @return string
     */
    public function getNotAvailableMessage($entity, $scope = EntityPaginationManager::VIEW_SCOPE)
    {
        $message = $this->translator->trans('oro.entity_pagination.message.not_available');

        $count = $this->navigation->getTotalCount($entity, $scope);
        if ($count) {
            $message .= ' ' . $this->translator->trans($this->getStatsMessage($scope), ['%count%' => $count]);
        }

        return $message;
    }

    /**
     * @param object $entity
     * @param string $scope
     * @return string
     */
    public function getNotAccessibleMessage($entity, $scope = EntityPaginationManager::VIEW_SCOPE)
    {
        $message = $this->translator->trans('oro.entity_pagination.message.not_accessible');

        $count = $this->navigation->getTotalCount($entity, $scope);
        if ($count) {
            $message .= ' ' . $this->translator->trans($this->getStatsMessage($scope), ['%count%' => $count]);
        }

        return $message;
    }

    /**
     * @param object $entity
     * @param string $scope
     * @return string|null
     */
    public function getInfoMessage($entity, $scope = EntityPaginationManager::VIEW_SCOPE)
    {
        $entityName = ClassUtils::getClass($entity);

        // info message should be shown only once for each scope
        if ($this->storage->isInfoMessageShown($entityName, $scope)) {
            return null;
        }

        $count = $this->navigation->getTotalCount($entity, $scope);
        if (!$count) {
            return null;
        }

        $message = '';

        // if scope is changing from "view" to "edit" and number of entities is decreased
        if ($scope == EntityPaginationManager::EDIT_SCOPE) {
            $viewCount = $this->navigation->getTotalCount($entity, EntityPaginationManager::VIEW_SCOPE);
            if ($viewCount && $count < $viewCount) {
                $message .= $this->translator->trans('oro.entity_pagination.message.stats_changed_view_to_edit') . ' ';
            }
        }

        $message .= $this->translator->trans($this->getStatsMessage($scope), ['%count%' => $count]);

        $this->storage->setInfoMessageShown($entityName, $scope);

        return $message;
    }

    /**
     * Result includes %count% placeholder
     *
     * @param string $scope
     * @return string
     * @throws \LogicException
     */
    protected function getStatsMessage($scope)
    {
        switch ($scope) {
            case EntityPaginationManager::VIEW_SCOPE:
                $message = 'oro.entity_pagination.message.stats_number_view_%count%';
                break;
            case EntityPaginationManager::EDIT_SCOPE:
                $message = 'oro.entity_pagination.message.stats_number_edit_%count%';
                break;
            default:
                throw new \LogicException(sprintf('Scope "%s" is not available.', $scope));
        }

        return $message;
    }
}
