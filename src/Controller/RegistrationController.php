<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        // Si ya está autenticado, redirigir al home
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Si se envió el formulario
        if ($request->isMethod('POST')) {
            // Obtener datos del formulario
            $email = $request->request->get('email');
            $name = $request->request->get('name');
            $lastname = $request->request->get('lastname');
            $password = $request->request->get('password');
            $passwordConfirm = $request->request->get('password_confirm');

            // Validaciones básicas
            $errors = [];

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido.';
            }

            if (empty($name)) {
                $errors[] = 'El nombre es obligatorio.';
            }

            if (empty($lastname)) {
                $errors[] = 'Los apellidos son obligatorios.';
            }

            if (empty($password) || strlen($password) < 6) {
                $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
            }

            if ($password !== $passwordConfirm) {
                $errors[] = 'Las contraseñas no coinciden.';
            }

            // Verificar si el email ya existe
            $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $errors[] = 'Este email ya está registrado.';
            }

            // Si hay errores, mostrarlos
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }

                return $this->render('registration/register.html.twig', [
                    'email' => $email,
                    'name' => $name,
                    'lastname' => $lastname,
                ]);
            }

            // Crear el usuario
            $user = new User();
            $user->setEmail($email);
            $user->setName($name);
            $user->setLastname($lastname);
            $user->setRoles(['ROLE_USER']); // Rol por defecto

            // ⚠️ PUNTO CRÍTICO: Hashear la contraseña (como hace el profesor)
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Guardar en base de datos
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', '¡Registro exitoso! Ya puedes iniciar sesión.');
            return $this->redirectToRoute('app_login');
        }

        // Mostrar formulario de registro
        return $this->render('registration/register.html.twig');
    }
}
