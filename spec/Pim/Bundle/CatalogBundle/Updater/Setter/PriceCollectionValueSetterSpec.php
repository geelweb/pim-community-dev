<?php

namespace spec\Pim\Bundle\CatalogBundle\Updater\Setter;

use PhpSpec\ObjectBehavior;
use Pim\Bundle\CatalogBundle\Builder\ProductBuilderInterface;
use Pim\Bundle\CatalogBundle\Exception\InvalidArgumentException;
use Pim\Bundle\CatalogBundle\Manager\CurrencyManager;
use Pim\Bundle\CatalogBundle\Model\AttributeInterface;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Model\ProductPriceInterface;
use Pim\Bundle\CatalogBundle\Model\ProductValue;
use Pim\Bundle\CatalogBundle\Model\ProductValueInterface;
use Pim\Bundle\CatalogBundle\Validator\AttributeValidatorHelper;
use Prophecy\Argument;

class PriceCollectionValueSetterSpec extends ObjectBehavior
{
    function let(
        ProductBuilderInterface $builder,
        CurrencyManager $currencyManager,
        AttributeValidatorHelper $attrValidatorHelper
    ) {
        $this->beConstructedWith(
            $builder,
            $attrValidatorHelper,
            $currencyManager,
            ['pim_catalog_price_collection']
        );
    }

    function it_is_a_setter()
    {
        $this->shouldImplement('Pim\Bundle\CatalogBundle\Updater\Setter\SetterInterface');
    }

    function it_supports_price_collection_attributes(
        AttributeInterface $price_collectionAttribute,
        AttributeInterface $textareaAttribute
    ) {
        $price_collectionAttribute->getAttributeType()->willReturn('pim_catalog_price_collection');
        $this->supports($price_collectionAttribute)->shouldReturn(true);
        $this->supportsAttribute($price_collectionAttribute)->shouldReturn(true);

        $textareaAttribute->getAttributeType()->willReturn('pim_catalog_textarea');
        $this->supports($textareaAttribute)->shouldReturn(false);
        $this->supportsAttribute($textareaAttribute)->shouldReturn(false);
    }

    function it_checks_locale_and_scope_when_setting_a_value(
        $attrValidatorHelper,
        $currencyManager,
        AttributeInterface $attribute,
        ProductInterface $product,
        ProductValueInterface $priceValue,
        ProductPriceInterface $price
    ) {
        $attrValidatorHelper->validateLocale(Argument::cetera())->shouldBeCalled();
        $attrValidatorHelper->validateScope(Argument::cetera())->shouldBeCalled();
        $currencyManager->getActiveCodes()->willReturn(['EUR', 'USD']);

        $attribute->getCode()->willReturn('price');
        $product->getValue('price', 'fr_FR', 'mobile')->willReturn($priceValue);
        $priceValue->getPrices()->willReturn([$price]);
        $price->setData(null)->shouldBeCalled();

        $data = [['data' => 123.2, 'currency' => 'EUR']];
        $this->setValue([$product], $attribute, $data, 'fr_FR', 'mobile');
    }

    function it_checks_locale_and_scope_when_setting_an_attribute_data(
        $attrValidatorHelper,
        $currencyManager,
        AttributeInterface $attribute,
        ProductInterface $product,
        ProductValueInterface $priceValue,
        ProductPriceInterface $price
    ) {
        $attrValidatorHelper->validateLocale(Argument::cetera())->shouldBeCalled();
        $attrValidatorHelper->validateScope(Argument::cetera())->shouldBeCalled();
        $currencyManager->getActiveCodes()->willReturn(['EUR', 'USD']);

        $attribute->getCode()->willReturn('price');
        $product->getValue('price', 'fr_FR', 'mobile')->willReturn($priceValue);
        $priceValue->getPrices()->willReturn([$price]);
        $price->setData(null)->shouldBeCalled();

        $data = [['data' => 123.2, 'currency' => 'EUR']];
        $this->setAttributeData($product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']);
    }

    function it_throws_an_error_if_data_is_not_an_array(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = 'not an array';

        $this->shouldThrow(
            InvalidArgumentException::arrayExpected('attributeCode', 'setter', 'prices collection', gettype($data))
        )->during('setAttributeData', [$product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']]);
    }

    function it_throws_an_error_if_data_does_not_contain_an_array(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = ['not an array'];

        $this->shouldThrow(
            InvalidArgumentException::arrayOfArraysExpected(
                'attributeCode',
                'setter',
                'prices collection',
                gettype($data)
            )
        )->during('setValue', [[$product], $attribute, $data, 'fr_FR', 'mobile']);
    }

    function it_throws_an_error_if_attribute_data_does_not_contain_an_array(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = ['not an array'];

        $this->shouldThrow(
            InvalidArgumentException::arrayOfArraysExpected(
                'attributeCode',
                'setter',
                'prices collection',
                gettype($data)
            )
        )->during('setAttributeData', [$product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']]);
    }

    function it_throws_an_error_if_data_value_does_not_contain_data_key(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = [['not the data key' => 123]];

        $this->shouldThrow(
            InvalidArgumentException::arrayKeyExpected(
                'attributeCode',
                'data',
                'setter',
                'prices collection',
                print_r($data, true)
            )
        )->during('setValue', [[$product], $attribute, $data, 'fr_FR', 'mobile']);
    }

    function it_throws_an_error_if_attribute_data_value_does_not_contain_data_key(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = [['not the data key' => 123]];

        $this->shouldThrow(
            InvalidArgumentException::arrayKeyExpected(
                'attributeCode',
                'data',
                'setter',
                'prices collection',
                print_r($data, true)
            )
        )->during('setAttributeData', [$product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']]);
    }

    function it_throws_an_error_if_data_value_contains_non_numeric_value(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = [['data' => 'non numeric value', 'currency' => 'EUR']];

        $this->shouldThrow(
            InvalidArgumentException::arrayNumericKeyExpected(
                'attributeCode',
                'data',
                'setter',
                'prices collection',
                gettype('text')
            )
        )->during('setValue', [[$product], $attribute, $data, 'fr_FR', 'mobile']);
    }

    function it_throws_an_error_if_attribute_data_value_contains_non_numeric_value(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = [['data' => 'non numeric value', 'currency' => 'EUR']];

        $this->shouldThrow(
            InvalidArgumentException::arrayNumericKeyExpected(
                'attributeCode',
                'data',
                'setter',
                'prices collection',
                gettype('text')
            )
        )->during('setAttributeData', [$product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']]);
    }

    function it_throws_an_error_if_data_value_does_not_contain_currency_key(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = [['data' => 123, 'not the currency key' => 'euro']];

        $this->shouldThrow(
            InvalidArgumentException::arrayKeyExpected(
                'attributeCode',
                'currency',
                'setter',
                'prices collection',
                print_r($data, true)
            )
        )->during('setValue', [[$product], $attribute, $data, 'fr_FR', 'mobile']);
    }

    function it_throws_an_error_if_attribute_data_value_does_not_contain_currency_key(
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $data = [['data' => 123, 'not the currency key' => 'euro']];

        $this->shouldThrow(
            InvalidArgumentException::arrayKeyExpected(
                'attributeCode',
                'currency',
                'setter',
                'prices collection',
                print_r($data, true)
            )
        )->during('setAttributeData', [$product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']]);
    }

    function it_throws_an_error_if_data_value_does_not_contain_valid_currency(
        $currencyManager,
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $currencyManager->getActiveCodes()->willReturn(['EUR', 'USD']);

        $data = [['data' => 123, 'currency' => 'invalid currency']];

        $this->shouldThrow(
            InvalidArgumentException::arrayInvalidKey(
                'attributeCode',
                'currency',
                'The currency does not exist',
                'setter',
                'prices collection',
                'invalid currency'
            )
        )->during('setValue', [[$product], $attribute, $data, 'fr_FR', 'mobile']);
    }

    function it_throws_an_error_if_attribute_data_value_does_not_contain_valid_currency(
        $currencyManager,
        AttributeInterface $attribute,
        ProductInterface $product
    ) {
        $attribute->getCode()->willReturn('attributeCode');

        $currencyManager->getActiveCodes()->willReturn(['EUR', 'USD']);

        $data = [['data' => 123, 'currency' => 'invalid currency']];

        $this->shouldThrow(
            InvalidArgumentException::arrayInvalidKey(
                'attributeCode',
                'currency',
                'The currency does not exist',
                'setter',
                'prices collection',
                'invalid currency'
            )
        )->during('setAttributeData', [$product, $attribute, $data, ['locale' => 'fr_FR', 'scope' => 'mobile']]);
    }

    function it_sets_a_price_collection_value_to_a_product_value(
        $builder,
        $currencyManager,
        AttributeInterface $attribute,
        ProductInterface $product1,
        ProductInterface $product2,
        ProductInterface $product3,
        ProductValue $productValue,
        ProductPriceInterface $price
    ) {
        $locale = 'fr_FR';
        $scope = 'mobile';
        $data = [['data' => 123.2, 'currency' => 'EUR']];

        $currencyManager->getActiveCodes()->willReturn(['EUR', 'USD']);

        $attribute->getCode()->willReturn('attributeCode');

        $builder
            ->addProductValue($product2, $attribute, $locale, $scope)
            ->willReturn($productValue);

        $product1->getValue('attributeCode', $locale, $scope)->shouldBeCalled()->willReturn($productValue);
        $product2->getValue('attributeCode', $locale, $scope)->willReturn(null);
        $product3->getValue('attributeCode', $locale, $scope)->willReturn($productValue);
        $productValue->getPrices()->willReturn([$price]);
        $price->setData(null)->shouldBeCalled();

        $products = [$product1, $product2, $product3];

        $builder->addPriceForCurrencyWithData($productValue, 'EUR', 123.2)->shouldBeCalled();
        $this->setValue($products, $attribute, $data, $locale, $scope);
    }

    function it_sets_a_attribute_data_price_collection_value_to_a_product_value(
        $builder,
        $currencyManager,
        AttributeInterface $attribute,
        ProductInterface $product1,
        ProductInterface $product2,
        ProductInterface $product3,
        ProductValue $productValue,
        ProductPriceInterface $price
    ) {
        $locale = 'fr_FR';
        $scope = 'mobile';
        $data = [['data' => 123.2, 'currency' => 'EUR']];

        $currencyManager->getActiveCodes()->willReturn(['EUR', 'USD']);

        $attribute->getCode()->willReturn('attributeCode');

        $builder
            ->addProductValue($product2, $attribute, $locale, $scope)
            ->willReturn($productValue);

        $product1->getValue('attributeCode', $locale, $scope)->shouldBeCalled()->willReturn($productValue);
        $product2->getValue('attributeCode', $locale, $scope)->willReturn(null);
        $product3->getValue('attributeCode', $locale, $scope)->willReturn($productValue);
        $productValue->getPrices()->willReturn([$price]);
        $price->setData(null)->shouldBeCalled();

        $builder->addPriceForCurrencyWithData($productValue, 'EUR', 123.2)->shouldBeCalled();
        $this->setattributeData($product1, $attribute, $data, ['locale' => $locale, 'scope' => $scope]);
        $this->setattributeData($product2, $attribute, $data, ['locale' => $locale, 'scope' => $scope]);
        $this->setattributeData($product3, $attribute, $data, ['locale' => $locale, 'scope' => $scope]);
    }
}
