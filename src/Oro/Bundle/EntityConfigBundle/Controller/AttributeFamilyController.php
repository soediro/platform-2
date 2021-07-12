<?php

namespace Oro\Bundle\EntityConfigBundle\Controller;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\ORM\EntityAliasResolver;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroup;
use Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeGroupRelation;
use Oro\Bundle\EntityConfigBundle\Entity\EntityConfigModel;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\EntityConfigBundle\Form\Type\AttributeFamilyType;
use Oro\Bundle\EntityConfigBundle\Manager\AttributeManager;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;
use Oro\Bundle\FormBundle\Model\UpdateHandler;
use Oro\Bundle\SecurityBundle\Annotation\CsrfProtection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Entity Attribute Family Controller
 * @Route("/attribute/family")
 */
class AttributeFamilyController extends AbstractController
{
    /**
     * @Route("/create/{alias}", name="oro_attribute_family_create")
     * @Template("@OroEntityConfig/AttributeFamily/update.html.twig")
     * @param string $alias
     * @return array|RedirectResponse
     */
    public function createAction($alias)
    {
        $entityConfigModel = $this->getEntityByAlias($alias);
        $attributeManager = $this->get(AttributeManager::class);

        $this->ensureEntityConfigSupported($entityConfigModel);

        $attributeFamily = new AttributeFamily();
        $attributeFamily->setEntityClass($entityConfigModel->getClassName());

        $defaultGroup = new AttributeGroup();
        $systemAttributes = $attributeManager->getSystemAttributesByClass($entityConfigModel->getClassName());

        /** @var FieldConfigModel $systemAttribute */
        foreach ($systemAttributes as $systemAttribute) {
            $attributeGroupRelation = new AttributeGroupRelation();
            $attributeGroupRelation->setEntityConfigFieldId($systemAttribute->getId());

            $defaultGroup->addAttributeRelation($attributeGroupRelation);
        }

        $translator = $this->getTranslator();
        $defaultGroup->setDefaultLabel(
            $translator->trans('oro.entity_config.form.default_group_label')
        );
        $attributeFamily->addAttributeGroup($defaultGroup);

        $response = $this->update(
            $attributeFamily,
            $translator->trans('oro.entity_config.controller.attribute_family.message.saved')
        );

        if (is_array($response)) {
            $response['entityAlias'] = $alias;
        }

        return $response;
    }

    /**
     * @Route("/update/{id}", name="oro_attribute_family_update")
     * @Template("@OroEntityConfig/AttributeFamily/update.html.twig")
     * @param AttributeFamily $attributeFamily
     * @return array|RedirectResponse
     */
    public function updateAction(AttributeFamily $attributeFamily)
    {
        $translator = $this->getTranslator();
        $successMsg = $translator->trans('oro.entity_config.attribute_family.message.updated');
        $response = $this->update($attributeFamily, $successMsg);

        if (is_array($response)) {
            $alias = $this->get(EntityAliasResolver::class)->getAlias($attributeFamily->getEntityClass());
            $response['entityAlias'] = $alias;
        }

        return $response;
    }

    /**
     * @param AttributeFamily $attributeFamily
     * @param string $message
     * @return array|RedirectResponse
     */
    protected function update(AttributeFamily $attributeFamily, $message)
    {
        $options['attributeEntityClass'] = $attributeFamily->getEntityClass();
        $form = $this->createForm(AttributeFamilyType::class, $attributeFamily, $options);

        $handler = $this->get(UpdateHandler::class);

        return $handler->update($attributeFamily, $form, $message);
    }

    /**
     * @Route("/index/{alias}", name="oro_attribute_family_index")
     * @Template()
     * @param string $alias
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction($alias)
    {
        $entityClass = $this->get(EntityAliasResolver::class)->getClassByAlias($alias);

        return [
            'params' => [
                'entity_class' => $entityClass,
            ],
            'alias' => $alias,
            'entity_class' => $entityClass,
            'attributeFamiliesLabel' => sprintf('oro.%s.menu.%s_attribute_families', $alias, $alias)
        ];
    }

    /**
     * @Route("/delete/{id}", name="oro_attribute_family_delete", methods={"DELETE"})
     * @CsrfProtection()
     * @param AttributeFamily $attributeFamily
     *
     * @return JsonResponse
     */
    public function deleteAction(AttributeFamily $attributeFamily)
    {
        $translator = $this->getTranslator();
        if ($this->isGranted('delete', $attributeFamily)) {
            $doctrineHelper = $this->get(DoctrineHelper::class);
            $entityManager = $doctrineHelper->getEntityManagerForClass(AttributeFamily::class);
            $entityManager->remove($attributeFamily);
            $entityManager->flush();
            $successful = true;
            $message = $translator->trans('oro.entity_config.attribute_family.message.deleted');
        } else {
            $successful = false;
            $message = $translator->trans('oro.entity_config.attribute_family.message.cant_delete');
        }

        return new JsonResponse(['message' => $message, 'successful' => $successful]);
    }

    /**
     * @Route("/view/{id}", name="oro_attribute_family_view", requirements={"id"="\d+"})
     * @Template()
     * @param \Oro\Bundle\EntityConfigBundle\Attribute\Entity\AttributeFamily $attributeFamily
     * @return array
     */
    public function viewAction(AttributeFamily $attributeFamily)
    {
        $aliasResolver = $this->get(EntityAliasResolver::class);

        return [
            'entity' => $attributeFamily,
            'entityAlias' => $aliasResolver->getAlias($attributeFamily->getEntityClass()),
        ];
    }

    /**
     * @param string $alias
     * @return EntityConfigModel
     */
    private function getEntityByAlias($alias)
    {
        $aliasResolver = $this->get(EntityAliasResolver::class);
        $entityClass = $aliasResolver->getClassByAlias($alias);

        $doctrineHelper = $this->get(DoctrineHelper::class);

        return $doctrineHelper->getEntityRepository(EntityConfigModel::class)
            ->findOneBy(['className' => $entityClass]);
    }

    /**
     * @param EntityConfigModel $entityConfigModel
     * @throws BadRequestHttpException
     */
    private function ensureEntityConfigSupported(EntityConfigModel $entityConfigModel)
    {
        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->get('oro_entity_config.provider.extend');
        $extendConfig = $extendConfigProvider->getConfig($entityConfigModel->getClassName());
        /** @var ConfigProvider $attributeConfigProvider */
        $attributeConfigProvider = $this->get('oro_entity_config.provider.attribute');
        $attributeConfig = $attributeConfigProvider->getConfig($entityConfigModel->getClassName());

        if (!$extendConfig->is('is_extend') || !$attributeConfig->is('has_attributes')) {
            throw new BadRequestHttpException(
                $this->getTranslator()->trans('oro.entity_config.attribute.entity_not_supported')
            );
        }
    }

    /**
     * @return TranslatorInterface
     */
    private function getTranslator(): TranslatorInterface
    {
        return $this->get(TranslatorInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array_merge(
            parent::getSubscribedServices(),
            [
                TranslatorInterface::class,
                UpdateHandler::class,
                EntityAliasResolver::class,
                AttributeManager::class,
                DoctrineHelper::class,
                'oro_entity_config.provider.extend' => ConfigProvider::class,
                'oro_entity_config.provider.attribute' => ConfigProvider::class,
            ]
        );
    }
}
