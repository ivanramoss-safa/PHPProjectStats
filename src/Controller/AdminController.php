<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\CategoryItem;
use App\Repository\CategoryRepository;
use App\Service\api\FootballApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_admin')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'categories' => $categories,
        ]);
    }



    #[Route('/category/new', name: 'app_admin_category_new')]
    public function newCategory(
        Request $request,
        EntityManagerInterface $em,
        FootballApiService $apiService
    ): Response {

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $type = $request->request->get('type');
            $description = $request->request->get('description');
            $selectedItems = $request->request->all('items'); 

            if (empty($name) || empty($type)) {
                $this->addFlash('error', 'El nombre y el tipo son obligatorios.');
                return $this->redirectToRoute('app_admin_category_new');
            }

            $category = new Category();
            $category->setName($name);
            $category->setType($type);
            $category->setDescription($description);

            $em->persist($category);

            if (!empty($selectedItems)) {
                foreach ($selectedItems as $externalId) {
                    $item = new CategoryItem();
                    $item->setCategory($category);
                    $item->setExternalId((int)$externalId);
                    $em->persist($item);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Categoría creada correctamente.');
            return $this->redirectToRoute('app_admin');
        }


        $availableItems = [];

        $topScorers = $apiService->getTopScorers(140, 2024);

        if (!empty($topScorers['response'])) {
            foreach ($topScorers['response'] as $item) {
                $availableItems[] = [
                    'id' => $item['player']['id'],
                    'name' => $item['player']['name'],
                    'photo' => $item['player']['photo'] ?? null,
                    'team' => $item['statistics'][0]['team']['name'] ?? '',
                    'goals' => $item['statistics'][0]['goals']['total'] ?? 0,
                ];
            }
        }

        return $this->render('admin/category_new.html.twig', [
            'availableItems' => $availableItems,
        ]);
    }



    #[Route('/category/{id}/edit', name: 'app_admin_category_edit')]
    public function editCategory(
        Category $category,
        Request $request,
        EntityManagerInterface $em,
        FootballApiService $apiService
    ): Response {
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');
            $selectedItems = $request->request->all('items');

            $category->setName($name);
            $category->setDescription($description);

            foreach ($category->getCategoryItems() as $item) {
                $em->remove($item);
            }

            if (!empty($selectedItems)) {
                foreach ($selectedItems as $externalId) {
                    $item = new CategoryItem();
                    $item->setCategory($category);
                    $item->setExternalId((int)$externalId);
                    $em->persist($item);
                }
            }

            $em->flush();

            $this->addFlash('success', 'Categoría actualizada correctamente.');
            return $this->redirectToRoute('app_admin');
        }

        $currentItems = [];
        foreach ($category->getCategoryItems() as $item) {
            $currentItems[] = $item->getExternalId();
        }

        $availableItems = [];

        if ($category->getType() === 'player') {
            $topScorers = $apiService->getTopScorers(140, 2024);
            if (!empty($topScorers['response'])) {
                foreach ($topScorers['response'] as $item) {
                    $availableItems[] = [
                        'id' => $item['player']['id'],
                        'name' => $item['player']['name'],
                        'photo' => $item['player']['photo'] ?? null,
                        'team' => $item['statistics'][0]['team']['name'] ?? '',
                    ];
                }
            }
        }

        return $this->render('admin/category_edit.html.twig', [
            'category' => $category,
            'availableItems' => $availableItems,
            'currentItems' => $currentItems,
        ]);
    }



    #[Route('/category/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'])]
    public function deleteCategory(
        Category $category,
        EntityManagerInterface $em
    ): Response {
        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Categoría eliminada correctamente.');
        return $this->redirectToRoute('app_admin');
    }
}
