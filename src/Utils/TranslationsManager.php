<?php

declare(strict_types=1);

namespace Bolt\Utils;

use Bolt\Entity\Field;
use Bolt\Entity\FieldParentInterface;
use Doctrine\Common\Collections\Collection;
use Knp\DoctrineBehaviors\Contract\Entity\TranslationInterface;

class TranslationsManager
{
    /** @var Collection */
    private $translations;

    /** @var Collection */
    private $keys;

    public function __construct(array $translations, array $keys)
    {
        $this->translations = $translations;
        $this->keys = $keys;
    }

    public function applyTranslations(Field $field, string $collectionName, $orderId)
    {
        if(! $field instanceof FieldParentInterface)
        {
            if($this->hasTranslations($field, $collectionName, $orderId))
            {
                $field->setTranslations($this->getTranslations($field, $collectionName, $orderId));
            }
        } else {
            /** @var Field $child */
            foreach ($field->getChildren() as $child) {
                $this->applyTranslations($child, $collectionName, $orderId);
            }
        }
    }

    private function getTranslations(Field $field, string $collectionName, $orderId): Collection
    {
        if (!$this->hasTranslations($field, $collectionName, $orderId)) {
            throw new \InvalidArgumentException(sprintf("'%s'does not have translations", $field->getName()));
        }

        if($field->hasParent())
        {
            $key = $this->keys[$collectionName][$field->getParent()->getName()][$orderId][$field->getName()]['value'];
        } else {
            $key = $this->keys[$collectionName][$field->getName()][$orderId]['value'];
        }

        $translations = $this->translations[$key];

        //do not return the translation for the current locale, so as to not override the newly submitted value
        $translationsWithoutCurrentLocale = $translations->filter(function (TranslationInterface $translation) use ($field){
            return $translation->getLocale() !== $field->getLocale();
        });

        return $translationsWithoutCurrentLocale;
    }

    private function hasTranslations(Field $field, string $collectionName, $orderId): bool
    {
        if ($field instanceof FieldParentInterface) {
            //assume FieldParentInterface always has translations (in order to process children)
            return true;
        }

        if($field->hasParent()) {
            //find key for field with a parent
            if (!(array_key_exists($collectionName, $this->keys)
                && array_key_exists($field->getParent()->getName(), $this->keys[$collectionName])
                && array_key_exists($orderId, $this->keys[$collectionName][$field->getParent()->getName()])
                && array_key_exists($field->getName(), $this->keys[$collectionName][$field->getParent()->getName()][$orderId])
                && array_key_exists('value', $this->keys[$collectionName][$field->getParent()->getName()][$orderId][$field->getName()]))) {
                // if $this->keys[$collectionName][$name][$order] does not exist, we can return.
                return false;
            }

            $key = $this->keys[$collectionName][$field->getParent()->getName()][$orderId][$field->getName()];
        } else {
            //find key for field without a parent
            if (!(array_key_exists($collectionName, $this->keys)
                && array_key_exists($field->getName(), $this->keys[$collectionName])
                && array_key_exists($orderId, $this->keys[$collectionName][$field->getName()])
                && array_key_exists('value', $this->keys[$collectionName][$field->getName()][$orderId]))) {
                // if $this->keys[$collectionName][$name][$order] does not exist, we can return.
                return false;
            }

            $key = $this->keys[$collectionName][$field->getName()][$orderId];
        }

        if (empty($key['value'] or !is_numeric($key['value']))) {
            // if key['value'] is empty or is not numeric (id), we can return.
            return false;
        }

        $value = (int)$key['value'];

        return array_key_exists($value, $this->translations);
    }
}