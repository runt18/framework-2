<?php
namespace Selenia\Matisse\Components\Base;

use Selenia\Interfaces\Views\ViewInterface;
use Selenia\ViewEngine\Engines\MatisseEngine;

/**
 * A component that delegates its rendering to a separate template (either internal or external to the component),
 * which is parsed, compiled and (in some cases) rendered by a view engine.
 *
 * <p>Composite components are composed of both a "source DOM" and a view (or "shadow DOM").
 *
 * <p>The source DOM is the set of original DOM subtrees (from children or from properties) provided to the component
 * on the document by its author. It can be used to provide metadata and/or document fragments for inclusion on the
 * view. This is the DOM that simple (non-composite) components work with.
 *
 * <p>Composite components do not render themselves directly, instead they delegate rendering to a view, which parses,
 * compiles and renders a template with the help of a view engine.
 *
 * <p>The view engine can be Matisse, in which case the view is compiled to a "shadow DOM" of components that can
 * render themselves, or it can be another templating engine, which usually is also responsible for rendering the
 * template.
 *
 * > <p>**Note:** Matisse components on the view can, in turn, be composite components that have their own templates,
 * and so on recursively. **But** the rendered output of a composite component must be final rendered markup, it can
 * not be again a template that requires further processing.
 */
class CompositeComponent extends Component
{
  /**
   * An inline/embedded template to be rendered as the component's appearance.
   *
   * <p>The view engine to be used to handle the template is selected by {@see $viewEngineClass}.
   *
   * @var string
   */
  public $template = '';
  /**
   * The URL of an external template to be loaded and rendered.
   *
   * <p>If specified, it takes precedence over {@see $template}.
   * <p>The view engine to be used to handle the external template is selected based on the file name extension.
   *
   * @var string
   */
  public $templateUrl = '';
  /**
   * When true, databinding resolution on the component's view is unnafected by data from parent component's models or
   * from the shared document view model (which is set on {@see Context}); only the component's own view model is used.
   *
   * <p>TODO: this is not implemented yet.
   *
   * @var bool
   */
  protected $isolateViewModel = false;
  /**
   * The engine to be used for parsing and rendering the view if {@see $template} is set and {@see $templateUrl} is not.
   *
   * @var string
   */
  protected $viewEngineClass = MatisseEngine::class;

  /**
   * Allows subclasses to generate the view's markup dinamically.
   * If not overriden, the default behaviour is to load the view from an external file, if one is defined on
   * `$templateUrl`. If not, the content of `$template` is returned, if set, otherwise no output is generated.
   *
   * > **Note:** this returns nothing; the output is sent directly to the output buffer.
   */
  protected function render ()
  {
    if ($this->templateUrl)
      $view = $this->context->viewService->loadFromFile ($this->templateUrl);
    elseif ($this->template)
      $view = $this->context->viewService->loadFromString ($this->template, $this->viewEngineClass);
    else return;

    $view->compile ();
    $this->setupView ($view);
    echo $view->render ();
  }

  /**
   * Allows access to the compiled view generated by the parsing process.
   * Component specific initialization can be performed here before the
   * page is rendered.
   * Override to add extra initialization.
   *
   * @param ViewInterface $view
   */
  protected function setupView (ViewInterface $view)
  {
    $engine = $view->getEngine ();
    if ($engine instanceof MatisseEngine) {
      /** @var Component $document */
      $document = $view->getCompiled ();
      $document->attachTo ($this);
    }
  }

}
