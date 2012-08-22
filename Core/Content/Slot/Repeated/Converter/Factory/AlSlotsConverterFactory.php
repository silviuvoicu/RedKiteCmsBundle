<?php
/*
 * This file is part of the AlphaLemon CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) AlphaLemon <webmaster@alphalemon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.alphalemon.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace AlphaLemon\AlphaLemonCmsBundle\Core\Content\Slot\Repeated\Converter\Factory;

use AlphaLemon\ThemeEngineBundle\Core\TemplateSlots\AlSlot;
use AlphaLemon\PageTreeBundle\Core\PageBlocks\AlPageBlocksInterface;
use AlphaLemon\AlphaLemonCmsBundle\Core\Repository\Factory\AlFactoryRepositoryInterface;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Content\Slot\SameRepeatedStatusException;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Content\General\ClassNotFoundException;

/**
 * Creates a slot converter from a known repeated status.
 *  *
 * @author alphalemon <webmaster@alphalemon.com>
 */
class AlSlotsConverterFactory implements AlSlotsConverterFactoryInterface
{
    protected $pageContentsContainer = null;
    protected $factoryRepository = null;

    /**
     * Constructor
     *
     * @param AlPageBlocksInterface $pageContentsContainer
     * @param AlFactoryRepositoryInterface $factoryRepository
     */
    public function __construct(AlPageBlocksInterface $pageContentsContainer, AlFactoryRepositoryInterface $factoryRepository)
    {
        $this->pageContentsContainer = $pageContentsContainer;
        $this->factoryRepository = $factoryRepository;
    }

    /**
     * Create the slot converter
     *
     * @param AlSlot $slot
     * @param type $newRepeatedStatus
     * @return \AlphaLemon\AlphaLemonCmsBundle\Core\Content\Slot\Repeated\Converter\Factory\className
     * @throws SameRepeatedStatusException
     * @throws ClassNotFoundException
     */
    public function createConverter(AlSlot $slot, $newRepeatedStatus)
    {
        if ($slot->getRepeated() == $newRepeatedStatus)
        {
            throw new SameRepeatedStatusException("The new repeated status you required is the same of the current slot. Aborted ");
        }

        $className = '\AlphaLemon\AlphaLemonCmsBundle\Core\Content\Slot\Repeated\Converter\AlSlotConverterTo' . ucfirst(strtolower($newRepeatedStatus));
        if(!class_exists($className)) {
            throw new ClassNotFoundException(sprintf("The class %s that shoud define a new Slot Converter does not exist", $className));
        }

        $slot->setRepeated($newRepeatedStatus);

        return new $className($slot, $this->pageContentsContainer, $this->factoryRepository);
    }
}