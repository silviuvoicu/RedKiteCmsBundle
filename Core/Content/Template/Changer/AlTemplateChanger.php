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

namespace AlphaLemon\AlphaLemonCmsBundle\Core\Content\Template\Changer;

use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Block\AlBlockManagerFactoryInterface;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Slot\AlSlotManager;
use AlphaLemon\ThemeEngineBundle\Core\TemplateSlots\AlSlot;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Template\AlTemplateManager;
use AlphaLemon\AlphaLemonCmsBundle\Core\Exception\Content\General;
use AlphaLemon\AlphaLemonCmsBundle\Core\Content\Validator\AlParametersValidatorInterface;

/**
 * Arranges the page's slot contents, when the page changes its template
 * 
 * 
 * Requires two Template Manager objects, which are both parsed and analysed to find
 * the slots presents on both the templates, the ones to add and the ones to remove.
 * 
 * When a new slot is added, the default value is used.
 *
 * @author alphalemon <webmaster@alphalemon.com>
 */
class AlTemplateChanger 
{
    protected $currentTemplateManager;
    protected $newTemplateManager; 
    protected $blockManagerFactory; 
    protected $parametersValidator; 
    
    public function __construct(AlBlockManagerFactoryInterface $blockManagerFactory = null, AlParametersValidatorInterface $parametersValidator = null)
    {
        $this->blockManagerFactory = $blockManagerFactory;
        $this->parametersValidator = $parametersValidator;
    }


    /**
     * Sets the current template used by the page
     * 
     * @api
     * @param AlTemplateManager $templateManager
     * @return \AlphaLemon\AlphaLemonCmsBundle\Core\Content\Template\Changer\AlTemplateChanger 
     */
    public function setCurrentTemplateManager(AlTemplateManager $templateManager)
    {
        $this->currentTemplateManager = $templateManager;
        
        return $this;
    }
    
    /**
     * Sets the new template the page will use
     * 
     * @api
     * @param AlTemplateManager $templateManager
     * @return \AlphaLemon\AlphaLemonCmsBundle\Core\Content\Template\Changer\AlTemplateChanger 
     */
    public function setNewTemplateManager(AlTemplateManager $templateManager)
    {
        $this->newTemplateManager = $templateManager;
        
        return $this;
    }
    
    /**
     * Arranges the page's contents accordig the new template's slots
     */
    public function change()
    {
        if (null === $this->currentTemplateManager) {
            throw new General\ParameterIsEmptyException("The current template manager has not been set. The tempèlate cannot be changed until this value is given");
        }
        
        if (null === $this->newTemplateManager) {
            throw new General\ParameterIsEmptyException("The current template manager has not been set. The tempèlate cannot be changed until this value is given");
        }
        
        $blockModel = $this->currentTemplateManager->getBlockModel();
        try
        {
            $operations = $this->analyse();
            
            $rollBack = false;
            $blockModel->startTransaction();
            foreach($operations as $operation => $slots) {
                switch($operation) {
                    case 'add':
                        foreach($slots as $repeated => $slotNames) {
                            foreach($slotNames as $slotName) {
                                $slot = new AlSlot($slotName, array('repeated' => $repeated));                            
                                $slotManager = new AlSlotManager($this->currentTemplateManager->getDispatcher(), $this->currentTemplateManager->getTranslator(), $slot, $blockModel, $this->parametersValidator, $this->blockManagerFactory);
                                $slotManager->setForceSlotAttributes(true);
                                
                                $pageContentsContainer = $this->currentTemplateManager->getPageContentsContainer();
                                $result = $slotManager->addBlock($pageContentsContainer->getIdLanguage(), $pageContentsContainer->getIdPage());
                                if (null !== $result) {     
                                    $rollBack = !$result;
                                    if($rollBack) break;
                                }
                            }
                            if($rollBack) break;
                        }
                        break;

                    case 'change':
                        foreach($slots as $intersections) {
                            foreach($intersections as $intersection) {
                                foreach($intersection as $repeated => $slotNames) {
                                    foreach($slotNames as $slotName) {
                                        // TODO
                                        /**/
                                        $slot = new AlSlot($slotName, array('repeated' => $repeated)); 
                                        $className = '\AlphaLemon\AlphaLemonCmsBundle\Core\Content\Slot\Repeated\Converter\AlSlotConverterTo' . ucfirst(strtolower($repeated));
                                        $converter = new $className($this->container, $slot, $this->alPage, $this->alLanguage);
                                        $rollBack = !$converter->convert();
                                        if($rollBack) break;
                                    }
                                    if($rollBack) break;
                                }
                                if($rollBack) break;
                            }
                            if($rollBack) break;
                        }
                        break;

                    case 'remove': 
                        foreach($slots as $slotNames) {
                            foreach($slotNames as $repeated =>  $slotName) {
                                $slot = new AlSlot($slotName, array('repeated' => $repeated));     
                                $slotManager = new AlSlotManager($this->currentTemplateManager->getDispatcher(), $this->currentTemplateManager->getTranslator(), $slot, $blockModel, $this->parametersValidator, $this->blockManagerFactory);
                                $blocks = $this->currentTemplateManager->getPageContentsContainer()->getSlotBlocks($slotName);
                                $slotManager->setUpBlockManagers($blocks);
                                $result = $slotManager->deleteBlocks();
                                if (null !== $result) { 
                                    if (!$result) {
                                        $rollBack = true;
                                        break;
                                    }
                                }
                            }
                        }
                        break;
                }
                if($rollBack) break;
            }

            if (!$rollBack) {
                $blockModel->commit(); 
                
                return true;
            }
            else {
                $blockModel->rollBack();
                
                return false;
            }
        }
        catch(\Exception $e) {
            if (isset($blockModel) && $blockModel !== null) {
                $blockModel->rollBack();
            }
            
            throw $e;
        }
    }

    /**
     * Analyzes both the templates and retrieves the slot's differences. A slot can be added, removed or changed,
     * where changed means that the slot has changed how the contents are repeated. 
     * 
     * This method fills up the operations array where are saved the information required to change the template
     */
    private function analyse()
    {
        $previousSlots = $this->currentTemplateManager->getTemplateSlots()->toArray();
        $newSlots = $this->newTemplateManager->getTemplateSlots()->toArray();
        
        $diffsForNew = $this->calculateDifferences($newSlots, $previousSlots);
        $diffsForPrevious = $this->calculateDifferences($previousSlots, $newSlots);
        
        $add = $this->calculateIntersections($diffsForNew, $diffsForPrevious); 
        $remove = $this->calculateIntersections($diffsForPrevious, $diffsForNew);
        
        $operations = array();
        $operations['add'] = (array_key_exists('found', $add)) ? $add['found'] : array();
        $operations['change'] = (array_key_exists('intersected', $add)) ? $add['intersected'] : array();
        $operations['remove'] = (array_key_exists('found', $remove)) ? $remove['found'] : array();
        
        return $operations;
    }
    
    /**
     * Calculates the differences between two arrays of slots
     * 
     * @param array $first
     * @param array $second
     * @return array 
     */
    private function calculateDifferences(array $first, array $second)
    {
        $result = array();
        foreach($first as $repeated => $slots) {
            $diff = array_diff($slots, $second[$repeated]);
            $result[$repeated] = $diff;
        }
        
        return $result;
    }
    
    /**
     * Calculates the intersections between the differences found on the arrays of slots
     * 
     * @param array $first
     * @param array $second
     * @return array
     */
    private function calculateIntersections(array $first, array $second)
    {
        $result = array();
        foreach($first as $aRepeated => $firstSlots) {
            $intersect = array();
            foreach($second as $bRepeated => $secondSlots) {
                $diff = array_intersect($firstSlots, $secondSlots); 
                if(!empty($diff)) {
                    $intersect[$bRepeated][$aRepeated] = $diff;
                    $firstSlots = array_diff($firstSlots, $diff); 
                }
            }
            
            if(!empty($firstSlots)) {
                $result['found'][$aRepeated] = $firstSlots;
            }
            
            if(!empty($intersect)) {
                $result['intersected'][] = $intersect;
            }
        }
        
        return $result;
    }
}