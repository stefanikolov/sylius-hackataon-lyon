<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Form\Type;

use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use Sylius\Bundle\ResourceBundle\Form\Type\AbstractResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class WishlistType extends AbstractResourceType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('name', TextType::class, [
            'label' => 'mark_app_sylius_wishlist.form.name',
            // The model setter does not accept null, so an empty field becomes an empty string and fails NotBlank.
            'empty_data' => '',
            'constraints' => [
                new NotBlank(),
                new Length(max: 100),
            ],
            'attr' => ['maxlength' => 100, 'autocomplete' => 'off'],
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'mark_app_sylius_wishlist_wishlist';
    }
}
