<?php

/**
 * Class used to embed new object forms in parent form
 *
 * @package    pxPropelEasyEmbeddedRelationPlugin
 * @subpackage form
 * @author     Jérémie Augustin <jeremie dot augustin at pixel-cookers dot com>
 * @author     Krzysztof Kotowicz <kkotowicz at gmail dot com>
 */
class pxNewRelationsContainerForm extends BaseForm
{
  public function configure()
  {
    $button = new pxNewRelationField(array(
      'containerName' => $this->getOption('containerName'),
      'addJavascript' => $this->getOption('addByCloning'),
      'useJSFramework' => $this->getOption('useJSFramework'),
      'newRelationButtonLabel' => $this->getOption('newRelationButtonLabel')
    ));

    if ($this->getOption('addByCloning')) $this->setWidget('_', $button);
  }

  /**
   * Moves button below embedded forms
   * @inheritdoc
   * @param string $name
   * @param sfForm $form
   * @param string $decorator
   */
  public function embedForm($name, sfForm $form, $decorator = null)
  {
    parent::embedForm($name, $form, $decorator);
    if ($this->getOption('addByCloning')) $this->widgetSchema->moveField('_', sfWidgetFormSchema::LAST);
  }
}