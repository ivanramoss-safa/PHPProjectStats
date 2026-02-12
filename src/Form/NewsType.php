<?php

namespace App\Form;

use App\Entity\News;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class , [
            'label' => 'Título',
            'attr' => ['class' => 'form-control']
        ])
            ->add('content', TextareaType::class , [
            'label' => 'Contenido',
            'attr' => ['class' => 'form-control', 'rows' => 5]
        ])
            ->add('image', TextType::class , [
            'label' => 'URL de imagen',
            'required' => false,
            'attr' => ['class' => 'form-control']
        ])
            ->add('category', ChoiceType::class , [
            'label' => 'Categoría',
            'choices' => [
                'General' => 'general',
                'Fichaje' => 'fichaje',
                'Lesión' => 'lesion',
                'Sanción' => 'sancion',
            ],
            'attr' => ['class' => 'form-select']
        ])
            ->add('featured', CheckboxType::class , [
            'label' => 'Destacar en Home',
            'required' => false,
        ])

            ->add('playerIds', HiddenType::class , [
            'required' => false,
            'mapped' => false,
        ])
            ->add('teamIds', HiddenType::class , [
            'required' => false,
            'mapped' => false,
        ])
            ->add('leagueIds', HiddenType::class , [
            'required' => false,
            'mapped' => false,
        ])
            ->add('coachIds', HiddenType::class , [
            'required' => false,
            'mapped' => false,
        ])
            ->add('venueIds', HiddenType::class , [
            'required' => false,
            'mapped' => false,
        ])
            ->add('fixtureIds', HiddenType::class , [
            'required' => false,
            'mapped' => false,
        ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => News::class ,
        ]);
    }
}