<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\CategoryItem;
use App\Entity\News;
use App\Form\NewsType;
use App\Repository\CategoryRepository;
use App\Repository\NewsRepository;
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
            $itemsRaw = $request->request->get('items', '');
            $selectedItems = $itemsRaw ? explode(',', $itemsRaw) : []; 

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
                $pId = $item['player']['id'];
                $availableItems[] = [
                    'id' => $pId,
                    'name' => $item['player']['name'],
                    'photo' => $item['player']['photo'] ?? null,
                    'team' => $apiService->getPlayerCurrentTeamName($pId, $item['statistics'] ?? []),
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
            $itemsRaw = $request->request->get('items', '');
            $selectedItems = $itemsRaw ? explode(',', $itemsRaw) : [];

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
        $currentItemsObjects = [];
        foreach ($category->getCategoryItems() as $item) {
            $extId = $item->getExternalId();
            $currentItems[] = $extId;

            $itemName = 'ID #' . $extId;
            $itemPhoto = null;
            $type = $category->getType();
            
            try {
                if ($type === 'player') {
                    $pData = $apiService->getCachedData('players', ['id' => $extId, 'season' => 2024], 86400);
                    if (!empty($pData['response'][0]['player'])) {
                        $p = $pData['response'][0]['player'];
                        $itemName = $p['name'] ?? $itemName;
                        $itemPhoto = $p['photo'] ?? null;
                    }
                } elseif ($type === 'team') {
                    $tData = $apiService->getCachedData('teams', ['id' => $extId], 86400);
                    if (!empty($tData['response'][0]['team'])) {
                        $t = $tData['response'][0]['team'];
                        $itemName = $t['name'] ?? $itemName;
                        $itemPhoto = $t['logo'] ?? null;
                    }
                } elseif ($type === 'league') {
                    $lData = $apiService->getCachedData('leagues', ['id' => $extId], 86400);
                    if (!empty($lData['response'][0]['league'])) {
                        $l = $lData['response'][0]['league'];
                        $itemName = $l['name'] ?? $itemName;
                        $itemPhoto = $l['logo'] ?? null;
                    }
                } elseif ($type === 'coach') {
                    $cData = $apiService->getCachedData('coachs', ['id' => $extId], 86400);
                    if (!empty($cData['response'][0])) {
                        $c = $cData['response'][0];
                        $itemName = $c['name'] ?? $itemName;
                        $itemPhoto = $c['photo'] ?? null;
                    }
                } elseif ($type === 'stadium') {
                    $vData = $apiService->getCachedData('venues', ['id' => $extId], 86400);
                    if (!empty($vData['response'][0])) {
                        $v = $vData['response'][0];
                        $itemName = $v['name'] ?? $itemName;
                        $itemPhoto = $v['image'] ?? null;
                    }
                }
            } catch (\Exception $e) {

            }
            
            $currentItemsObjects[] = [
                'id' => $extId,
                'name' => $itemName,
                'photo' => $itemPhoto
            ];
        }

        $availableItems = [];

        if ($category->getType() === 'player') {
            $topScorers = $apiService->getTopScorers(140, 2024);
            if (!empty($topScorers['response'])) {
                foreach ($topScorers['response'] as $item) {
                    $pId = $item['player']['id'];
                    $availableItems[] = [
                        'id' => $pId,
                        'name' => $item['player']['name'],
                        'photo' => $item['player']['photo'] ?? null,
                        'team' => $apiService->getPlayerCurrentTeamName($pId, $item['statistics'] ?? []),
                    ];
                }
            }
        }

        return $this->render('admin/category_edit.html.twig', [
            'category' => $category,
            'availableItems' => $availableItems,
            'currentItems' => $currentItems,
            'currentItemsObjects' => $currentItemsObjects,
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

    #[Route('/news', name: 'admin_news_index')]
    public function newsIndex(NewsRepository $newsRepository): Response
    {
        return $this->render('admin/news/index.html.twig', [
            'noticias' => $newsRepository->findBy([], ['createdAt' => 'DESC'])
        ]);
    }

    #[Route('/news/new', name: 'admin_news_new')]
    public function newsNew(Request $request, EntityManagerInterface $em): Response
    {
        $news = new News();

        $prefill = $request->getSession()->get('prefill_news');
        if ($prefill) {
            $request->getSession()->remove('prefill_news');
            $news->setTitle($prefill['title'] ?? '');
            $news->setContent($prefill['content'] ?? '');
            $news->setCategory($prefill['category'] ?? null);
            if (!empty($prefill['sourceType'])) {
                $news->setSourceType($prefill['sourceType']);
            }
            if (!empty($prefill['sourceData'])) {
                $news->setSourceData($prefill['sourceData']);
            }
        }

        $form = $this->createForm(NewsType::class, $news);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $playerIdsString = $form->get('playerIds')->getData();
            if ($playerIdsString) {
                $news->setPlayerIds(array_map('intval', explode(',', $playerIdsString)));
            }
            
            $teamIdsString = $form->get('teamIds')->getData();
            if ($teamIdsString) {
                $news->setTeamIds(array_map('intval', explode(',', $teamIdsString)));
            }
            
            $leagueIdsString = $form->get('leagueIds')->getData();
            if ($leagueIdsString) {
                $news->setLeagueIds(array_map('intval', explode(',', $leagueIdsString)));
            }

            $coachIdsString = $form->get('coachIds')->getData();
            if ($coachIdsString) {
                $news->setCoachIds(array_map('intval', explode(',', $coachIdsString)));
            }

            $venueIdsString = $form->get('venueIds')->getData();
            if ($venueIdsString) {
                $news->setVenueIds(array_map('intval', explode(',', $venueIdsString)));
            }

            $fixtureIdsString = $form->get('fixtureIds')->getData();
            if ($fixtureIdsString) {
                $news->setFixtureIds(array_map('intval', explode(',', $fixtureIdsString)));
            }

            $news->setAuthor($this->getUser());
            $em->persist($news);
            $em->flush();

            $this->addFlash('success', 'Noticia creada correctamente');
            return $this->redirectToRoute('admin_news_index');
        }

        $fixtureId = $request->query->get('fixtureId');

        return $this->render('admin/news/form.html.twig', [
            'form' => $form,
            'editing' => false,
            'prefilledFixtureId' => $fixtureId,
        ]);
    }

    #[Route('/news/{id}/edit', name: 'admin_news_edit')]
    public function newsEdit(News $news, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(NewsType::class, $news);

        $form->get('playerIds')->setData(implode(',', $news->getPlayerIds() ?? []));
        $form->get('teamIds')->setData(implode(',', $news->getTeamIds() ?? []));
        $form->get('leagueIds')->setData(implode(',', $news->getLeagueIds() ?? []));
        $form->get('coachIds')->setData(implode(',', $news->getCoachIds() ?? []));
        $form->get('venueIds')->setData(implode(',', $news->getVenueIds() ?? []));
        $form->get('fixtureIds')->setData(implode(',', $news->getFixtureIds() ?? []));
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $playerIdsString = $form->get('playerIds')->getData();
            $news->setPlayerIds($playerIdsString ? array_map('intval', explode(',', $playerIdsString)) : []);
            
            $teamIdsString = $form->get('teamIds')->getData();
            $news->setTeamIds($teamIdsString ? array_map('intval', explode(',', $teamIdsString)) : []);
            
            $leagueIdsString = $form->get('leagueIds')->getData();
            $news->setLeagueIds($leagueIdsString ? array_map('intval', explode(',', $leagueIdsString)) : []);

            $coachIdsString = $form->get('coachIds')->getData();
            $news->setCoachIds($coachIdsString ? array_map('intval', explode(',', $coachIdsString)) : []);

            $venueIdsString = $form->get('venueIds')->getData();
            $news->setVenueIds($venueIdsString ? array_map('intval', explode(',', $venueIdsString)) : []);

            $fixtureIdsString = $form->get('fixtureIds')->getData();
            $news->setFixtureIds($fixtureIdsString ? array_map('intval', explode(',', $fixtureIdsString)) : []);

            $em->flush();

            $this->addFlash('success', 'Noticia actualizada correctamente');
            return $this->redirectToRoute('admin_news_index');
        }

        return $this->render('admin/news/form.html.twig', [
            'form' => $form,
            'editing' => true,
            'news' => $news,
        ]);
    }

    #[Route('/news/{id}/delete', name: 'admin_news_delete', methods: ['POST', 'GET'])]
    public function newsDelete(News $news, EntityManagerInterface $em): Response
    {
        $em->remove($news);
        $em->flush();

        $this->addFlash('success', 'Noticia eliminada correctamente');
        return $this->redirectToRoute('admin_news_index');
    }




    #[Route('/news/from-transfer', name: 'admin_news_from_transfer')]
    public function newsFromTransfer(Request $request): Response
    {
        $playerName = $request->query->get('player_name', '');
        $from       = $request->query->get('from', '');
        $to         = $request->query->get('to', '');
        $type       = $request->query->get('type', 'Transfer');
        $date       = $request->query->get('date', date('Y-m-d'));

        $request->getSession()->set('prefill_news', [
            'title'      => "Fichaje: {$playerName} ({$from} -> {$to})",
            'content'    => "El jugador {$playerName} ha sido traspasado desde {$from} a {$to} ({$type}) el {$date}.",
            'category'   => 'fichaje',
            'sourceType' => 'transfer',
            'sourceData' => [
                'player_name' => $playerName,
                'from' => $from,
                'to' => $to,
                'type' => $type,
                'date' => $date,
            ],
        ]);

        return $this->redirectToRoute('admin_news_new');
    }

    #[Route('/news/from-injury', name: 'admin_news_from_injury')]
    public function newsFromInjury(Request $request): Response
    {
        $playerName = $request->query->get('player_name', '');
        $team       = $request->query->get('team', '');
        $reason     = $request->query->get('reason', '');
        $type       = $request->query->get('type', 'Missing Fixture');

        $request->getSession()->set('prefill_news', [
            'title'      => "Lesion: {$playerName} ({$team})",
            'content'    => "{$playerName} ({$team}) se encuentra lesionado. Motivo: {$reason}. Estado: {$type}.",
            'category'   => 'lesion',
            'sourceType' => 'injury',
            'sourceData' => [
                'player_name' => $playerName,
                'team' => $team,
                'reason' => $reason,
                'type' => $type,
            ],
        ]);

        return $this->redirectToRoute('admin_news_new');
    }
}
