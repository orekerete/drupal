<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Template\TwigExtensionTest.
 */

namespace Drupal\Tests\Core\Template;

use Drupal\Core\GeneratedLink;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Template\TwigExtension;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the twig extension.
 *
 * @group Template
 *
 * @coversDefaultClass \Drupal\Core\Template\TwigExtension
 */
class TwigExtensionTest extends UnitTestCase {

  /**
   * Tests the escaping
   *
   * @dataProvider providerTestEscaping
   */
  public function testEscaping($template, $expected) {
    $renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');
    $twig = new \Twig_Environment(NULL, array(
      'debug' => TRUE,
      'cache' => FALSE,
      'autoescape' => 'html',
      'optimizations' => 0
    ));
    $twig->addExtension((new TwigExtension($renderer))->setUrlGenerator($this->getMock('Drupal\Core\Routing\UrlGeneratorInterface')));

    $nodes = $twig->parse($twig->tokenize($template));

    $this->assertSame($expected, $nodes->getNode('body')
        ->getNode(0)
        ->getNode('expr') instanceof \Twig_Node_Expression_Filter);
  }

  /**
   * Provides tests data for testEscaping
   *
   * @return array
   *   An array of test data each containing of a twig template string and
   *   a boolean expecting whether the path will be safe.
   */
  public function providerTestEscaping() {
    return array(
      array('{{ path("foo") }}', FALSE),
      array('{{ path("foo", {}) }}', FALSE),
      array('{{ path("foo", { foo: "foo" }) }}', FALSE),
      array('{{ path("foo", foo) }}', TRUE),
      array('{{ path("foo", { foo: foo }) }}', TRUE),
      array('{{ path("foo", { foo: ["foo", "bar"] }) }}', TRUE),
      array('{{ path("foo", { foo: "foo", bar: "bar" }) }}', TRUE),
      array('{{ path(name = "foo", parameters = {}) }}', FALSE),
      array('{{ path(name = "foo", parameters = { foo: "foo" }) }}', FALSE),
      array('{{ path(name = "foo", parameters = foo) }}', TRUE),
      array(
        '{{ path(name = "foo", parameters = { foo: ["foo", "bar"] }) }}',
        TRUE
      ),
      array('{{ path(name = "foo", parameters = { foo: foo }) }}', TRUE),
      array(
        '{{ path(name = "foo", parameters = { foo: "foo", bar: "bar" }) }}',
        TRUE
      ),
    );
  }

  /**
   * Tests the active_theme function.
   */
  public function testActiveTheme() {
    $renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');
    $extension = new TwigExtension($renderer);
    $theme_manager = $this->getMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $active_theme = $this->getMockBuilder('\Drupal\Core\Theme\ActiveTheme')
      ->disableOriginalConstructor()
      ->getMock();
    $active_theme
      ->expects($this->once())
      ->method('getName')
      ->willReturn('test_theme');
    $theme_manager
      ->expects($this->once())
      ->method('getActiveTheme')
      ->willReturn($active_theme);
    $extension->setThemeManager($theme_manager);

    $loader = new \Twig_Loader_String();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($extension);
    $result = $twig->render('{{ active_theme() }}');
    $this->assertEquals('test_theme', $result);
  }

  /**
   * Tests the format_date filter.
   */
  public function testFormatDate() {
    $date_formatter = $this->getMockBuilder('\Drupal\Core\Datetime\DateFormatter')
      ->disableOriginalConstructor()
      ->getMock();
    $date_formatter->expects($this->exactly(2))
      ->method('format')
      ->willReturn('1978-11-19');
    $renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');
    $extension = new TwigExtension($renderer);
    $extension->setDateFormatter($date_formatter);

    $loader = new StringLoader();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($extension);
    $result = $twig->render('{{ time|format_date("html_date") }}');
    $this->assertEquals($date_formatter->format('html_date'), $result);
  }

  /**
   * Tests the active_theme_path function.
   */
  public function testActiveThemePath() {
    $renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');
    $extension = new TwigExtension($renderer);
    $theme_manager = $this->getMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $active_theme = $this->getMockBuilder('\Drupal\Core\Theme\ActiveTheme')
      ->disableOriginalConstructor()
      ->getMock();
    $active_theme
      ->expects($this->once())
      ->method('getPath')
      ->willReturn('foo/bar');
    $theme_manager
      ->expects($this->once())
      ->method('getActiveTheme')
      ->willReturn($active_theme);
    $extension->setThemeManager($theme_manager);

    $loader = new \Twig_Loader_String();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($extension);
    $result = $twig->render('{{ active_theme_path() }}');
    $this->assertEquals('foo/bar', $result);
  }

  /**
   * Tests the escaping of objects implementing MarkupInterface.
   *
   * @covers ::escapeFilter
   */
  public function testSafeStringEscaping() {
    $renderer = $this->getMock('\Drupal\Core\Render\RendererInterface');
    $twig = new \Twig_Environment(NULL, array(
      'debug' => TRUE,
      'cache' => FALSE,
      'autoescape' => 'html',
      'optimizations' => 0
    ));
    $twig_extension = new TwigExtension($renderer);

    // By default, TwigExtension will attempt to cast objects to strings.
    // Ensure objects that implement MarkupInterface are unchanged.
    $safe_string = $this->getMock('\Drupal\Component\Render\MarkupInterface');
    $this->assertSame($safe_string, $twig_extension->escapeFilter($twig, $safe_string, 'html', 'UTF-8', TRUE));

    // Ensure objects that do not implement MarkupInterface are escaped.
    $string_object = new TwigExtensionTestString("<script>alert('here');</script>");
    $this->assertSame('&lt;script&gt;alert(&#039;here&#039;);&lt;/script&gt;', $twig_extension->escapeFilter($twig, $string_object, 'html', 'UTF-8', TRUE));
  }

  /**
   * @covers ::safeJoin
   */
  public function testSafeJoin() {
    $renderer = $this->prophesize(RendererInterface::class);
    $renderer->render(['#markup' => '<strong>will be rendered</strong>', '#printed' => FALSE])->willReturn('<strong>will be rendered</strong>');
    $renderer = $renderer->reveal();

    $twig_extension = new TwigExtension($renderer);
    $twig_environment = $this->prophesize(TwigEnvironment::class)->reveal();

    // Simulate t().
    $markup = $this->prophesize(TranslatableMarkup::class);
    $markup->__toString()->willReturn('<em>will be markup</em>');
    $markup = $markup->reveal();

    $items = [
      '<em>will be escaped</em>',
      $markup,
      ['#markup' => '<strong>will be rendered</strong>']
    ];
    $result = $twig_extension->safeJoin($twig_environment, $items, '<br/>');
    $this->assertEquals('&lt;em&gt;will be escaped&lt;/em&gt;<br/><em>will be markup</em><br/><strong>will be rendered</strong>', $result);

    // Ensure safe_join Twig filter supports Traversable variables.
    $items = new \ArrayObject([
      '<em>will be escaped</em>',
      $markup,
      ['#markup' => '<strong>will be rendered</strong>'],
    ]);
    $result = $twig_extension->safeJoin($twig_environment, $items, ', ');
    $this->assertEquals('&lt;em&gt;will be escaped&lt;/em&gt;, <em>will be markup</em>, <strong>will be rendered</strong>', $result);

    // Ensure safe_join Twig filter supports empty variables.
    $items = NULL;
    $result = $twig_extension->safeJoin($twig_environment, $items, '<br>');
    $this->assertEmpty($result);
  }

  /**
   * @dataProvider providerTestRenderVar
   */
  public function testRenderVar($result, $input) {
    $renderer = $this->prophesize(RendererInterface::class);
    $renderer->render($result += ['#printed' => FALSE])->willReturn('Rendered output');

    $renderer = $renderer->reveal();
    $twig_extension = new TwigExtension($renderer);

    $this->assertEquals('Rendered output', $twig_extension->renderVar($input));
  }

  public function providerTestRenderVar() {
    $data = [];

    $renderable = $this->prophesize(RenderableInterface::class);
    $render_array = ['#type' => 'test', '#var' => 'giraffe'];
    $renderable->toRenderable()->willReturn($render_array);
    $data['renderable'] = [$render_array, $renderable->reveal()];

    return $data;
  }

  /**
   * @covers ::escapeFilter
   * @covers ::bubbleArgMetadata
   */
  public function testEscapeWithGeneratedLink() {
    $renderer = $this->prophesize(RendererInterface::class);
    $twig = new \Twig_Environment(NULL, [
        'debug' => TRUE,
        'cache' => FALSE,
        'autoescape' => 'html',
        'optimizations' => 0,
      ]
    );

    $twig_extension = new TwigExtension($renderer->reveal());
    $twig->addExtension($twig_extension->setUrlGenerator($this->prophesize(UrlGeneratorInterface::class)->reveal()));
    $link = new GeneratedLink();
    $link->setGeneratedLink('<a href="http://example.com"></a>');
    $link->addCacheTags(['foo']);
    $link->addAttachments(['library' => ['system/base']]);

    $result = $twig_extension->escapeFilter($twig, $link, 'html', NULL, TRUE);
    $renderer->render([
      "#cache" => [
        "contexts" => [],
        "tags" => ["foo"],
        "max-age" => -1
      ],
      "#attached" => ['library' => ['system/base']],
    ])->shouldHaveBeenCalled();
    $this->assertEquals('<a href="http://example.com"></a>', $result);
  }

  /**
   * @covers ::renderVar
   * @covers ::bubbleArgMetadata
   */
  public function testRenderVarWithGeneratedLink() {
    $renderer = $this->prophesize(RendererInterface::class);
    $twig_extension = new TwigExtension($renderer->reveal());
    $link = new GeneratedLink();
    $link->setGeneratedLink('<a href="http://example.com"></a>');
    $link->addCacheTags(['foo']);
    $link->addAttachments(['library' => ['system/base']]);

    $result = $twig_extension->renderVar($link);
    $renderer->render([
      "#cache" => [
        "contexts" => [],
        "tags" => ["foo"],
        "max-age" => -1
      ],
      "#attached" => ['library' => ['system/base']],
    ])->shouldHaveBeenCalled();
    $this->assertEquals('<a href="http://example.com"></a>', $result);
  }

  /**
   * Tests creating attributes within a Twig template.
   *
   * @covers ::createAttribute
   */
  public function testCreateAttribute() {
    $renderer = $this->prophesize(RendererInterface::class);
    $extension = new TwigExtension($renderer->reveal());
    $loader = new StringLoader();
    $twig = new \Twig_Environment($loader);
    $twig->addExtension($extension);

    $iterations = [
      ['class' => ['kittens'], 'data-toggle' => 'modal', 'data-lang' => 'es'],
      ['id' => 'puppies', 'data-value' => 'foo', 'data-lang' => 'en'],
      [],
    ];
    $result = $twig->render("{% for iteration in iterations %}<div{{ create_attribute(iteration) }}></div>{% endfor %}", ['iterations' => $iterations]);
    $expected = '<div class="kittens" data-toggle="modal" data-lang="es"></div><div id="puppies" data-value="foo" data-lang="en"></div><div></div>';
    $this->assertEquals($expected, $result);

    // Test default creation of empty attribute object and using its method.
    $result = $twig->render("<div{{ create_attribute().addClass('meow') }}></div>");
    $expected = '<div class="meow"></div>';
    $this->assertEquals($expected, $result);
  }

}

class TwigExtensionTestString {

  protected $string;

  public function __construct($string) {
    $this->string = $string;
  }

  public function __toString() {
    return $this->string;
  }

}
