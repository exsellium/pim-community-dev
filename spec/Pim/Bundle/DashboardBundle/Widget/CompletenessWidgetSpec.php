<?php

namespace spec\Pim\Bundle\DashboardBundle\Widget;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Pim\Bundle\CatalogBundle\Model\ProductRepositoryInterface;

class CompletenessWidgetSpec extends ObjectBehavior
{
    function let(ProductRepositoryInterface $repository)
    {
        $this->beConstructedWith($repository);
    }

    function it_is_a_widget()
    {
        $this->shouldBeAnInstanceOf('Pim\Bundle\DashboardBundle\Widget\WidgetInterface');
    }

    function it_exposes_the_completeness_widget_template()
    {
        $this->getTemplate()->shouldReturn('PimDashboardBundle:Widget:completeness.html.twig');
    }

    function it_exposes_the_completeness_template_parameters($repository)
    {
        $repository->countProductsPerChannels()->willReturn(array(
            array(
                'label' => 'Mobile',
                'total' => 40,
            ),
            array(
                'label' => 'E-Commerce',
                'total' => 25,
            ),
        ));
        $repository->countCompleteProductsPerChannels()->willReturn(array(
            array(
                'label'  => 'Mobile',
                'locale' => 'en_US',
                'total'  => 10,
            ),
            array(
                'label'  => 'Mobile',
                'locale' => 'fr_FR',
                'total'  => 0,
            ),
            array(
                'label'  => 'E-Commerce',
                'locale' => 'en_US',
                'total'  => 25,
            ),
            array(
                'label'  => 'E-Commerce',
                'locale' => 'fr_FR',
                'total'  => 5,
            ),
        ));

        $this->getParameters()->shouldReturn(
            array(
                'params' => array(
                    'Mobile' => array(
                        'total'    => 40,
                        'complete' => 10,
                        'locales'  => array(
                            'en_US' => 10,
                            'fr_FR' => 0,
                        ),
                    ),
                    'E-Commerce' => array(
                        'total' => 25,
                        'complete' => 30,
                        'locales' => array(
                            'en_US' => 25,
                            'fr_FR' => 5,
                        )
                    )
                )
            )
        );
    }
}
