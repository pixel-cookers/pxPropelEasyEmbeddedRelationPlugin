<?php

/**
 * This class displays the button to add new embedded relation forms; it relies on client-side JavaScript to work.
 * @author
 */
class pxNewRelationField extends sfWidgetForm
{
  protected function configure($options = array(), $attributes = array())
  {
    $this->addRequiredOption('containerName');
    $this->addOption('addJavascript', false);
    $this->addOption('useJSFramework', 'jQuery');
    $this->addOption('newRelationButtonLabel', '+');
  }

  public function render($name, $value = null, $attributes = array(), $errors = array())
  {
    return $this->renderContentTag('button', $this->getOption('newRelationButtonLabel'), array(
      'type' => 'button', 'class' => 'pxAddRelation', 'rel' => $this->getOption('containerName')));
  }

  public function getJavaScripts()
  {
    if (false === $this->getOption('addJavascript')) return array();

    // allow only 0-9,a-z,A-Z,- and _ for framework name (LFI protection)
    $cleanFrameworkName = preg_replace('#[^0-9a-z._-]#i', '', $this->getOption('useJSFramework'));

    $filename = sprintf('pxPropelEasyEmbeddedRelationsPlugin.%s.js', $cleanFrameworkName);
    return array('/pxPropelEasyEmbeddedRelationsPlugin/js/' . $filename);
  }
}