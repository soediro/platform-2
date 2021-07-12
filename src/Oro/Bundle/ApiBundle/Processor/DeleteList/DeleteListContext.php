<?php

namespace Oro\Bundle\ApiBundle\Processor\DeleteList;

use Oro\Bundle\ApiBundle\Processor\ChangeContextInterface;
use Oro\Bundle\ApiBundle\Processor\ListContext;

/**
 * The execution context for processors for "delete_list" action.
 */
class DeleteListContext extends ListContext implements ChangeContextInterface
{
    /**
     * {@inheritdoc}
     */
    public function getAllEntities(bool $mainOnly = false): array
    {
        $entities = $this->getResult();
        if (null === $entities) {
            return [];
        }

        return $entities;
    }
}
