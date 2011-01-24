<?php

/**
 * Propel form base class that makes it pretty easy to embed one or multiple related forms including creation forms.
 *
 * @package    pxPropelEasyEmbeddedRelationPlugin
 * @subpackage form
 * @author     Jérémie Augustin <jeremie dot augustin at pixel-cookers dot com>
 *
 * Authors of the Doctrine version:
 * @author     Daniel Lohse <info@asaphosting.de>
 * @author     Krzysztof Kotowicz <kkotowicz at gmail dot com>
 * @author     Gadfly <gadfly@linux-coders.org>
 * @author     Fabrizio Bottino <fabryb@fabryb.com>
 * @author     Matt Daum <matt@setfive.com>
 */
abstract class pxBaseFormPropel extends sfFormPropel
{
  protected
    $scheduledForDeletion = array(), // related objects scheduled for deletion
    $embedRelations = array(),       // so we can check which relations are embedded in this form
    $defaultRelationSettings = array(
        'considerNewFormEmptyFields' => array(),
        'noNewForm' => false,
        'newFormLabel' => null,
        'newFormClass' => null,
        'newFormClassArgs' => array(),
        'newFormUnsetPrimaryKeys'=>true, // Sometimes you may not want to hide them, if they are a composite key for example
        'formClass' => null,
        'formClassArgs' => array(),
        'displayEmptyRelations' => false,
        'newFormAfterExistingRelations' => false,
        'customEmbeddedFormLabelMethod' => null,
        'formFormatter' => null,
        'multipleNewForms' => false,
        'newFormsInitialCount' => 2,
        'newFormsContainerForm' => null, // pass BaseForm object here or we will create pxNewRelationsContainerForm
        'newRelationButtonLabel' => '+',
        'newRelationAddByCloning' => true,
        'newRelationUseJSFramework' => 'jQuery',
        'isUnique'  => false, // True to handle duplicate form base on a field "uniqueField"
        'uniqueField' => null, //name of the field that should be unique
        'fusionOrDeleteUniqueField' => 'fusion', // fusion or delete (duplicated entry)
        'fusionFields' => array(), // array(array('field_name' => 'my_field', 'fusion_method' => 'myMethodToFusion'), array()) the default method is addition : fusionFieldAddition
    );

  protected function addDefaultRelationSettings(array $settings)
  {
    return array_merge($this->defaultRelationSettings, $settings);
  }

  public function embedRelations(array $relations)
  {
    $this->embedRelations = $relations;

    $this->getEventDispatcher()->connect('form.post_configure', array($this, 'listenToFormPostConfigureEvent'));

    foreach ($relations as $relationName => $relationSettings)
    {
      $relationSettings = $this->addDefaultRelationSettings($relationSettings);
      $relationMap = $this->getRelationMap($relationName);
      if ($relationMap->getType() != RelationMap::ONE_TO_MANY)
      {
        throw new sfException('embedRelations() only works for one-to-many relationships');
      }

      $collection = call_user_func(array($this->getObject(), sprintf('get%ss', $relationName)));

      if (!$relationSettings['noNewForm'])
      {
        $containerName = 'new_'.$relationName;
        $formLabel = $relationSettings['newFormLabel'];

        if($relationMap->getType() != RelationMap::ONE_TO_ONE)
        {
          if ($relationSettings['multipleNewForms']) // allow multiple new forms for this relation
          {
            $newFormsCount = $relationSettings['newFormsInitialCount'];

            $subForm = $this->newFormsContainerFormFactory($relationSettings, $containerName);
            for ($i = 0; $i < $newFormsCount; $i++)
            {
              // we need to create new forms with cloned object inside (otherwise only the last new values would be saved)
              $newForm = $this->embeddedFormFactory($relationName, $relationSettings, $relationMap, $i + 1);
              $subForm->embedForm($i, $newForm);
            }
            $subForm->getWidgetSchema()->setLabel($formLabel);
            $this->embedForm($containerName, $subForm);
          }
          else // just a single new form for this relation
          {
            $newForm = $this->embeddedFormFactory($relationName, $relationSettings, $relationMap, $formLabel);
            $this->embedForm($containerName, $newForm);
          }
        }
        //TODO fix relatedExists($relationName
        // elseif ($relationMap->getType() == RelationMap::ONE_TO_ONE && !$this->getObject()->relatedExists($relationName))
//        elseif ($relationMap->getType() == RelationMap::ONE_TO_ONE)
//        {
//          $newForm = $this->embeddedFormFactory($relationName, $relationSettings, $relationMap, $formLabel);
//          $this->embedForm($containerName, $newForm);
//        }
      }

      $formClass = (null === $relationSettings['formClass']) ? $relationMap->getLocalTable()->getClassname().'Form' : $relationSettings['formClass'];
      $formArgs = (null === $relationSettings['formClassArgs']) ? array() : $relationSettings['formClassArgs'];
      if ((isset($formArgs[0]) && !array_key_exists('px_add_delete_checkbox', $formArgs[0])) || !isset($formArgs[0]))
      {
        $formArgs[0]['px_add_delete_checkbox'] = true;
      }

      if($relationMap->getType() == RelationMap::ONE_TO_ONE)
      {
//        $form = new $formClass($this->getObject()->$relationName, $formArgs[0]);
//        $this->embedForm($relationName, $form);
//
//	      foreach($relationMap->getLocalColumns() as $fk)
//	      {
//	        unset($newForm[strtolower($fk->getName())]);
//	      }
        //maybe we need this: if (!$this->getObject()->relatedExists($relationName))
        //unset($this[$relation->getLocalColumnName()]);
      }
      else
      {
        $subForm = new sfForm();


        foreach ($collection as $index => $childObject)
        {
        	if(!$childObject->isNew())
        	{
	          $form = new $formClass($childObject, $formArgs[0]);

	          $subForm->embedForm($index, $form);
	          // check if existing embedded relations should have a different label
	          if (null === $relationSettings['customEmbeddedFormLabelMethod'] || !method_exists($childObject, $relationSettings['customEmbeddedFormLabelMethod']))
	          {
	            $subForm->getWidgetSchema()->setLabel($index, (string)$childObject);
	          }
	          else
	          {
	            $subForm->getWidgetSchema()->setLabel($index, $childObject->$relationSettings['customEmbeddedFormLabelMethod']());
	          }
        	}
        }

        $this->embedForm($relationName, $subForm);
      }

      if ($relationSettings['formFormatter']) // switch formatter
      {
        $widget = $this[$relationName]->getWidget()->getWidget();
        $widget->setFormFormatterName($relationSettings['formFormatter']);
        // not only we have to change formatter name
        // but also recreate schemadecorator as there is no setter for decorator in sfWidgetFormSchemaDecorator :(
        $this->widgetSchema[$relationName] = new sfWidgetFormSchemaDecorator($widget, $widget->getFormFormatter()->getDecoratorFormat());
      }

      /*
       * Unset the relation form(s) if:
       * (1. One-to-many relation and there are no related objects yet (count of embedded forms is 0) OR
       * 2. One-to-one relation and embedded form is new (no related object yet))
       * AND
       * (3. Option `displayEmptyRelations` was either not set by the user or was set by the user and is false)
       */
      if(( count($this->getEmbeddedForm($relationName)->getEmbeddedForms()) === 0) && !$relationSettings['displayEmptyRelations'])
      {
        unset($this[$relationName]);
      }

      if (
        $relationSettings['newFormAfterExistingRelations'] &&
        isset($this[$relationName]) && isset($this['new_'.$relationName])
      )
      {
        $this->getWidgetSchema()->moveField('new_'.$relationName, sfWidgetFormSchema::AFTER, $relationName);
      }
    }

    $this->getEventDispatcher()->disconnect('form.post_configure', array($this, 'listenToFormPostConfigureEvent'));
  }

  public function listenToFormPostConfigureEvent(sfEvent $event)
  {
    $form = $event->getSubject();

    if ($form instanceof sfFormPropel && $form->getOption('px_add_delete_checkbox', false) && !$form->isNew())
    {
      $form->setWidget('delete_object', new sfWidgetFormInputCheckbox(array('label' => 'Delete')));
      $form->setValidator('delete_object', new sfValidatorPass());

      return $form;
    }

    return false;
  }

  /**
   * Here we just drop the embedded creation forms if no value has been
   * provided for them (this simulates a non-required embedded form),
   * please provide the fields for the related embedded form in the call
   * to $this->embedRelations() so we don't throw validation errors
   * if the user did not want to add a new related object
   *
   * @see sfForm::doBind()
   */
  protected function doBind(array $values)
  {
    $values = $this->doBindEmbedRelations($this, $values);

    parent::doBind($values);
  }

  protected function doBindEmbedRelations($form, array $values)
  {
    // iterate over all embeded relations
    foreach ($form->embedRelations as $relationName => $keys)
    {
      $keys = $form->addDefaultRelationSettings($keys);

      // check if new form exists
      if (!$keys['noNewForm'])
      {
        $containerName = 'new_'.$relationName;

        if ($keys['multipleNewForms']) // just a single new form for this relation
        {
          if (array_key_exists($containerName, $values))
          {
            foreach ($values[$containerName] as $index => $subFormValues)
            {
              if ($form->isNewFormEmpty($subFormValues, $keys))
              {
                unset($values[$containerName][$index], $form->embeddedForms[$containerName][$index], $form->validatorSchema[$containerName][$index]);
              }
              else
              {
                // if new forms were inserted client-side, embed them here
                if (!isset($form->embeddedForms[$containerName][$index]))
                {
                  // create and embed new form
                  $relationMap = $this->getRelationMap($relationName);
                  $addedForm = $this->embeddedFormFactory($relationName, $keys, $relationMap, ((int) $index) + 1);
                  $ef = $form->embeddedForms[$containerName];
                  $ef->embedForm($index, $addedForm);
                  // ... and reset other stuff (symfony loses all this since container form is already embedded)
                  $form->validatorSchema[$containerName] = $ef->getValidatorSchema();
                  $form->widgetSchema[$containerName] = new sfWidgetFormSchemaDecorator($ef->getWidgetSchema(), $ef->getWidgetSchema()->getFormFormatter()->getDecoratorFormat());
                  $form->setDefault($containerName, $ef->getDefaults());
                }
              }
            }
          }

          $form->validatorSchema[$containerName] = $form->embeddedForms[$containerName]->getValidatorSchema();

          if(array_key_exists($containerName, $values))
          {
	          // check for new forms that were deleted client-side and never submitted
	          foreach (array_keys($form->embeddedForms[$containerName]->embeddedForms) as $index)
	          {
	            if (!array_key_exists($index, $values[$containerName]))
	            {
	                unset($form->embeddedForms[$containerName][$index], $form->validatorSchema[$containerName][$index]);
	            }
	          }
          }

          // all new forms were empty
          if(array_key_exists($containerName, $values))
          {
	          if (count($values[$containerName]) === 0)
	          {
	            unset($values[$containerName], $form->validatorSchema[$containerName]);
	          }
          }
          else
          {
          	unset($form->validatorSchema[$containerName]);
          }
        }
        else
        {
          // remove new form when it is empty
          if (!array_key_exists($containerName, $values) || $form->isNewFormEmpty($values[$containerName], $keys))
          {
            unset($values[$containerName], $form->embeddedForms[$containerName], $form->validatorSchema[$containerName]);
          }
        }
      }

      if (isset($values[$relationName]))
      {
      	$relationMap         = $form->getRelationMap($relationName);
        $relationForm        = $form->embeddedForms[$relationName];
      	$oneToOneRelationFix = $relationMap->getType() == RelationMap::ONE_TO_ONE ? array($values[$relationName]) : $values[$relationName];

        // Get the column(s) for the primary key, composite ones have multiple
        //$relationPrimaryKeys=Doctrine::getTable($relation->getClass())->getIdentifierColumnNames();
        $relationPrimaryKeys = $relationMap->getLocalTable()->getPrimaryKeys();

        foreach ($oneToOneRelationFix as $i => $relationValues)
        {
          if (isset($relationValues['delete_object']))
          {
            $primaryKeyValues=array();
            //foreach($relationPrimaryKeys as $pkName)
            foreach($relationPrimaryKeys as $pkName)
            {
            	$pkName = strtolower($pkName->getName());
              $primaryKeyValues[$pkName]=$relationValues[$pkName];
            }
            $form->scheduledForDeletion[$relationName][$i] = $primaryKeyValues;

            // not validate forms that should be marked for deleting
            if($relationMap->getType() == RelationMap::ONE_TO_ONE)
            {
              unset($values[$relationName], $form->validatorSchema[$relationName]);
            }
            else
            {
              unset($values[$relationName][$i], $relationForm->validatorSchema[$i]);
            }
          }
          else
          {
            // walk recursive over embeded forms
            if($relationMap->getType() == RelationMap::ONE_TO_ONE)
            {
              $values[$relationName] = $form->doBindEmbedRelations($relationForm, $values[$relationName]);
            }
            else
            {
              $relationFormEntry = $relationForm->embeddedForms[$i];
              $values[$relationName][$i] = $form->doBindEmbedRelations($relationFormEntry, $values[$relationName][$i]);

              // only not bounded forms can be embed
              $relationForm->isBound = false;
              $relationFormEntry->isBound = false;

              // bind entry form to relation form
              $relationForm->embedForm($i, $relationFormEntry);

              // revert bound state
              $relationFormEntry->isBound = true;
              $relationForm->isBound = true;
            }
          }
        }

        // only not bounded forms can be embed
        $form->isBound = false;
        $relationForm->isBound = false;

        // bind relation form
        $form->embedForm($relationName, $relationForm);

        // revert bound state
        $relationForm->isBound = true;
        $form->isBound = true;
      }
    }

    return $values;
  }

  /**
   * Updates object with provided values, dealing with eventual relation deletion
   *
   * @see sfFormDoctrine::doUpdateObject()
   */
  protected function doUpdateObject($values)
  {
    if (count($this->getScheduledForDeletion()) > 0)
    {
      foreach ($this->getScheduledForDeletion() as $relationName => $ids)
      {
        $relationMap = $this->getRelationMap($relationName);
        $collection = call_user_func(array($this->getObject(), sprintf('get%ss', $relationName)));
        foreach ($ids as $index => $id)
        {
          if($relationMap->getType() == RelationMap::ONE_TO_ONE)
          {
            unset($values[$relationName]);
          }
          else
          {
            unset($values[$relationName][$index]);
          }

          if($relationMap->getType() != RelationMap::ONE_TO_ONE)
          {
            unset($this->widgetSchema[$relationName][$index]);
		        unset($this->validatorSchema[$relationName][$index]);
		        unset($this->defaults[$relationName][$index]);
		        unset($this->taintedValues[$relationName][$index]);
		        unset($this->values[$relationName][$index]);
		        unset($this->embeddedForms[$relationName][$index]);

          }
          $queryClass = $relationMap->getLocalTable()->getClassname(). 'Query';
          $queryClass::create()->filterByPrimaryKey($id['id'])->delete();
        }
      }
    }

    parent::doUpdateObject($values);
  }

/**
   * Overwride Symfony method to
   * Updates the values of the object with the cleaned up values.
   *
   * @param  array $values An array of values
   *
   * @return mixed The current updated object
   */
  public function updateObject($values = null)
  {
    if (null === $values)
    {
      $values = $this->values;
    }

    $values = $this->processValues($values);

    $this->doUpdateObject($values);

    //Clean unique value for embeddedForms
    $values = $this->doCleanUnique($values);
    // embedded forms
    $this->updateObjectEmbeddedForms($values);

    return $this->getObject();
  }

  public function doCleanUnique($values)
  {
  	foreach ($this->embedRelations as $relationName => $keys)
    {
      $keys = $this->addDefaultRelationSettings($keys);


      if($keys['isUnique'])
      {
      	$array_unique = array();
      	$array_unique_new = array();

      	if (isset($values[$relationName]))
        {
        	// old values
        	foreach($values[$relationName] as $index => $form)
        	{
        		if(($unique_exist = array_search($values[$relationName][$index][$keys['uniqueField']], $array_unique)) === false)
        		{
        			$array_unique[$index] = $values[$relationName][$index][$keys['uniqueField']];
        		}
        		else
        		{
        			switch($keys['fusionOrDeleteUniqueField'])
        			{
        				case 'fusion':
        					foreach($keys['fusionFields'] as $fusionField)
        					{
        						if(!isset($fusionField['fusion_method']))
        						{
        							$fusionField['fusion_method'] = 'fusionFieldAddition';
        						}
        						if(method_exists($this, $fusionField['fusion_method'])) // isset($values[$relationName][$unique_exist][$keys['field_name']]))
        						{
        							$values[$relationName][$unique_exist][$fusionField['field_name']] =
        							 $this->{$fusionField['fusion_method']}($values[$relationName][$unique_exist][$fusionField['field_name']], $values[$relationName][$index][$fusionField['field_name']]);
        						}
        					}

        			  case 'delete':
			            unset($this->widgetSchema[$relationName][$index]);
			            unset($this->validatorSchema[$relationName][$index]);
			            unset($this->defaults[$relationName][$index]);
			            unset($this->taintedValues[$relationName][$index]);
			            unset($this->values[$relationName][$index]);
			            unset($this->embeddedForms[$relationName][$index]);
			            $relationMap = $this->getRelationMap($relationName);
				          $queryClass = $relationMap->getLocalTable()->getClassname(). 'Query';
				          $queryClass::create()->filterByPrimaryKey($form['id'])->delete();
                  break;
        			}
        		}
        	}
        }

        $containerName = 'new_'.$relationName;

        if (isset($values[$containerName]))
        {
          // new values
          foreach($values[$containerName] as $index => $form)
          {
            if(($unique_exist = array_search($values[$containerName][$index][$keys['uniqueField']], $array_unique)) === false
              && ($unique_exist = array_search($values[$containerName][$index][$keys['uniqueField']], $array_unique_new)) === false)
            {
              $array_unique_new[$index] = $values[$containerName][$index][$keys['uniqueField']];
            }
            else
            {

            	if(($unique_exist = array_search($values[$containerName][$index][$keys['uniqueField']], $array_unique)) !== false)
            	{
            		$duplicateNew = false;
            	}
            	else if (($unique_exist = array_search($values[$containerName][$index][$keys['uniqueField']], $array_unique_new)) !== false)
              {
              	$duplicateNew = true;
              }
              switch($keys['fusionOrDeleteUniqueField'])
              {
                case 'fusion':
                  foreach($keys['fusionFields'] as $fusionField)
                  {
                    if(!isset($fusionField['fusion_method']))
                    {
                      $fusionField['fusion_method'] = 'fusionFieldAddition';
                    }
                    if(method_exists($this, $fusionField['fusion_method']) )
                    {
                    	if($duplicateNew && isset($values[$containerName][$unique_exist][$keys['field_name']]))
                    	{
                        $values[$containerName][$unique_exist][$fusionField['field_name']] =
                            $this->{$fusionField['fusion_method']}($values[$containerName][$unique_exist][$fusionField['field_name']], $values[$containerName][$index][$fusionField['field_name']]);
                    	}
                    	else if(!$duplicateNew && isset($values[$relationName][$unique_exist][$fusionField['field_name']]))
                      {
                        $values[$relationName][$unique_exist][$fusionField['field_name']] =
                            $this->{$fusionField['fusion_method']}($values[$relationName][$unique_exist][$fusionField['field_name']], $values[$containerName][$index][$fusionField['field_name']]);
                      }
                    }
                  }

                case 'delete':
                  unset($this->widgetSchema[$containerName][$index]);
                  unset($this->validatorSchema[$containerName][$index]);
                  unset($this->defaults[$containerName][$index]);
                  unset($this->taintedValues[$containerName][$index]);
                  unset($this->values[$containerName][$index]);
                  unset($this->embeddedForms[$containerName][$index]);
                  break;
              }
            }
          }
        }
      }
    }

//    print_r($values);
//    die();

  	return $values;
  }

  public function getScheduledForDeletion()
  {
    return $this->scheduledForDeletion;
  }

  /**
   * Saves embedded form objects.
   *
   * @param mixed $con   An optional connection object
   * @param array $forms An array of sfForm instances
   *
   * @see sfFormObject::saveEmbeddedForms()
   */
  public function saveEmbeddedForms($con = null, $forms = null)
  {
    if (null === $con) $con = $this->getConnection();
    if (null === $forms) $forms = $this->getEmbeddedForms();

    foreach ($forms as $form)
    {
      if ($form instanceof sfFormObject)
      {
        /**
         * we know it's a form but we don't know what (embedded) relation it represents;
         * this is necessary because we only care about the relations that we(!) embedded
         * so there isn't anything weird happening
         */
        $relationName = $this->getRelationByEmbeddedFormClass($form);

        if ($relationName && isset($this->scheduledForDeletion[$relationName]) && false!==array_search($form->getObject()->getPrimaryKey(),$this->scheduledForDeletion[$relationName]))
        {
          continue;
        }

        $form->saveEmbeddedForms($con);
        // this is Propel specific
        if(isset($form->getObject()->markForDeletion))
        {
          $form->getObject()->delete($con);
        }
        else
        {
          $form->getObject()->save($con);
        }
      }
      else
      {
        $this->saveEmbeddedForms($con, $form->getEmbeddedForms());
      }
    }
  }

  /**
   * Get the used relation alias when given an embedded form
   *
   * @param sfForm $form A BaseForm instance
   */
  private function getRelationByEmbeddedFormClass($form)
  {
  	$classPeer = $form->getPeer();
  	$relations = array();
  	if(is_object($classPeer))
  	{
  	  $relations = $classPeer->getTableMap()->getRelations();
  	}
    foreach ($relations as $relation)
    {
    	$class = $relation->getLocalTable()->getClassname();
     // $class = $relation->getClass();
      if ($form->getObject() instanceof $class)
      {
        return $relation->getName();
      }
    }

    return false;
  }

  /**
     * Get the used relation alias when given an object
     *
     * @param $object
     */
    private function getRelationAliasByObject($object)
    {
    	$classPeer = $object->getPeer();
	    $relations = array();
	    if(is_object($classPeer))
	    {
	      $relations = $classPeer->getTableMap()->getRelations();
	    }

      foreach ($relations as $relation)
      {
      	$class = $relation->getLocalTable()->getClassname();
        if ($this->getObject() instanceof $class)
        {
        	return $relation->getName();
        }
      }
    }

  /**
   * Checks if given form values for new form are 'empty' (i.e. should the form be discarded)
   *
   * @param array $values
   * @param array $keys settings for the embedded relation
   * @return bool
   */
  protected function isNewFormEmpty(array $values, array $keys)
  {
    if (count($keys['considerNewFormEmptyFields']) == 0 || !isset($values)) return false;

    $emptyFields = 0;
    foreach ($keys['considerNewFormEmptyFields'] as $key)
    {
      if (is_array($values[$key]))
      {
        if (count($values[$key]) === 0)
        {
          $emptyFields++;
        }
        elseif (array_key_exists('tmp_name', $values[$key]) && $values[$key]['tmp_name'] === '' && $values[$key]['size'] === 0)
        {
          $emptyFields++;
        }
      }
      elseif ('' === trim($values[$key]))
      {
        $emptyFields++;
      }
    }

    if ($emptyFields === count($keys['considerNewFormEmptyFields']))
    {
      return true;
    }

    return false;
  }

  /**
   * Creates and initializes new form object for a given relation.
   * @internal
   * @param string $relationName
   * @param array $relationSettings
   * @param Doctrine_Relation $relation
   * @param string $formLabel
   * @return sfFormDoctrine
   */
  private function embeddedFormFactory($relationName, array $relationSettings, RelationMap $relationMap, $formLabel = null)
  {
      $newFormObject = $this->embeddedFormObjectFactory($relationName, $relationMap);
      $formClass = (null === $relationSettings['newFormClass']) ? $relationMap->getLocalTable()->getClassname().'Form' : $relationSettings['newFormClass'];
      $formArgs = (null === $relationSettings['newFormClassArgs']) ? array() : $relationSettings['newFormClassArgs'];
      $r = new ReflectionClass($formClass);

      /* @var $newForm sfFormObject */
      $newForm = $r->newInstanceArgs(array_merge(array($newFormObject), $formArgs));

      if($relationSettings['newFormUnsetPrimaryKeys'])
      {
        $newFormIdentifiers = $newForm->getObject()->getPeer()->getTableMap()->getPrimaryKeys();
        foreach ($newFormIdentifiers as $primaryKey)
        {
          unset($newForm[strtolower($primaryKey->getName())]);
        }
      }

		  foreach($relationMap->getLocalColumns() as $fk)
			{
			  unset($newForm[strtolower($fk->getName())]);
			}

      // FIXME/TODO: check if this even works for one-to-one
      // CORRECTION 1: Not really, it creates another record but doesn't link it to this object!
      // CORRECTION 2: No, it can't, silly! For that to work the id of the not-yet-existant related record would have to be known...
      // Think about overriding the save method and after calling parent::save($con) we should update the relations that:
      //   1. are one-to-one AND
      //   2. are LocalKey :)
      if (null !== $formLabel)
      {
        $newForm->getWidgetSchema()->setLabel($formLabel);
      }

      return $newForm;
  }

  /**
   * Returns Propel object prepared for form given the relation
   * @param string $relationName
   * @param RelationMap $relation
   *
   */
  private function embeddedFormObjectFactory($relationName, RelationMap $relation)
  {
    if($relation->getType() != RelationMap::ONE_TO_ONE)
    {
      $newFormObjectClass = $relation->getLocalTable()->getClassname();

      $newFormObject = new $newFormObjectClass();

      //Limitation: need to set ColumnId to prevent object from being saved by the related object in case of deletion
      //TODO: replace by PK, FK
      $newFormObject->{'set'. get_class($this->getObject()).'Id'}($this->getObject()->getId());
    }

    return $newFormObject;
  }

  /**
   * Create and initialize form that will embed 'newly created relation' subforms
   * If no object is given in 'newFormsContainerForm' parameter, it will
   * initialize custom form bundled with this plugin
   * @param array $relationSettings
   * @return sfForm (pxNewRelationsContainerForm by default)
   */
  private function newFormsContainerFormFactory(array $relationSettings, $containerName)
  {
    $subForm = $relationSettings['newFormsContainerForm'];

    if (null === $subForm)
    {
      $subForm = new pxNewRelationsContainerForm(null, array(
        'containerName' => $containerName,
        'addByCloning' => $relationSettings['newRelationAddByCloning'],
        'useJSFramework' => $relationSettings['newRelationUseJSFramework'],
        'newRelationButtonLabel' => $relationSettings['newRelationButtonLabel']
      ));
    }

    if ($relationSettings['formFormatter']) {
      $subForm->getWidgetSchema()->setFormFormatterName($relationSettings['formFormatter']);
    }

    unset($subForm[$subForm->getCSRFFieldName()]);
    return $subForm;
  }


  /**
   *
   * Addition $firstValue with $secondValue
   * @param unknown_type $firstValue
   * @param unknown_type $secondValue
   */
  public function fusionFieldAddition($firstValue, $secondValue)
  {
  	return ($firstValue + $secondValue);
  }
}
