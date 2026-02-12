<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class , [
            'label' => 'Valoración',
            'choices' => [
                '⭐⭐⭐⭐⭐ (Excelente)' => 5,
                '⭐⭐⭐⭐ (Muy bueno)' => 4,
                '⭐⭐⭐ (Bueno)' => 3,
                '⭐⭐ (Regular)' => 2,
                '⭐ (Malo)' => 1,
            ],
            'expanded' => true, 
            'multiple' => false,
            'data' => 5,
            'attr' => ['class' => 'd-flex justify-content-between gap-2 flex-wrap mb-3 review-rating-options'],
            'label_attr' => ['class' => 'fw-bold mb-2'],
        ])
            ->add('comment', TextareaType::class , [
            'label' => 'Tu opinión (opcional)',
            'required' => false,
            'attr' => [
                'rows' => 3,
                'class' => 'form-control',
                'placeholder' => 'Comparte tu opinión sobre este elemento...'
            ],
            'constraints' => [
                new Length(max: 1000, maxMessage: 'El comentario no puede superar los {{ limit }} caracteres.'),
            ],
        ])
            ->add('season', \Symfony\Component\Form\Extension\Core\Type\IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'd-none'], 
                'label' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class ,
        ]);
    }
}
