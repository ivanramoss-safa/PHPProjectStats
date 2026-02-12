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




    #[Route('/api/search/players', name: 'admin_api_search_players')]
    public function searchPlayers(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        $league = $request->query->getInt('league', 140);
        $team = $request->query->getInt('team', 0);
        
        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $params = ['search' => $query, 'season' => 2024];
        if ($team > 0) {
            $params['team'] = $team;
        } else {
            $params['league'] = $league;
        }
        
        $data = $apiService->getCachedData('players', $params, 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['player']['id'],
                'name' => $item['player']['name'],
                'photo' => $item['player']['photo'] ?? null,
                'extra' => $item['statistics'][0]['team']['name'] ?? '',
            ];
        }

        return $this->json([
            'results' => $results,
            'hasMore' => count($results) >= 20,
        ]);
    }

    #[Route('/api/search/teams-by-league', name: 'admin_api_teams_by_league')]
    public function getTeamsByLeague(Request $request, FootballApiService $apiService): Response
    {
        $league = $request->query->getInt('league', 140);
        
        $data = $apiService->getTeamsByLeague($league, 2024);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['team']['id'],
                'name' => $item['team']['name'],
            ];
        }

        usort($results, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $this->json(['results' => $results]);
    }

    #[Route('/api/search/teams', name: 'admin_api_search_teams')]
    public function searchTeams(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $data = $apiService->getCachedData('teams', [
            'search' => $query,
        ], 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['team']['id'],
                'name' => $item['team']['name'],
                'photo' => $item['team']['logo'] ?? null,
                'extra' => $item['team']['country'] ?? '',
            ];
        }

        return $this->json(['results' => $results, 'hasMore' => false]);
    }

    #[Route('/api/search/leagues', name: 'admin_api_search_leagues')]
    public function searchLeagues(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $queryNoSpaces = str_replace(' ', '', $query);
        $queryWithSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', $query);

        $data = $apiService->getCachedData('leagues', ['search' => $query], 3600);
        $results = [];
        
        foreach ($data['response'] ?? [] as $item) {
            $results[$item['league']['id']] = [
                'id' => $item['league']['id'],
                'name' => $item['league']['name'],
                'photo' => $item['league']['logo'] ?? null,
                'extra' => $item['country']['name'] ?? '',
            ];
        }

        if (empty($results) && $queryNoSpaces !== $query) {
            $data2 = $apiService->getCachedData('leagues', ['search' => $queryNoSpaces], 3600);
            foreach ($data2['response'] ?? [] as $item) {
                $results[$item['league']['id']] = [
                    'id' => $item['league']['id'],
                    'name' => $item['league']['name'],
                    'photo' => $item['league']['logo'] ?? null,
                    'extra' => $item['country']['name'] ?? '',
                ];
            }
        }

        if (empty($results) && $queryWithSpaces !== $query) {
            $data3 = $apiService->getCachedData('leagues', ['search' => $queryWithSpaces], 3600);
            foreach ($data3['response'] ?? [] as $item) {
                $results[$item['league']['id']] = [
                    'id' => $item['league']['id'],
                    'name' => $item['league']['name'],
                    'photo' => $item['league']['logo'] ?? null,
                    'extra' => $item['country']['name'] ?? '',
                ];
            }
        }

        return $this->json(['results' => array_values($results), 'hasMore' => false]);
    }

    #[Route('/api/search/coaches', name: 'admin_api_search_coaches')]
    public function searchCoaches(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $data = $apiService->getCachedData('coachs', [
            'search' => $query,
        ], 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'photo' => $item['photo'] ?? null,
                'extra' => $item['team']['name'] ?? '',
            ];
        }

        return $this->json(['results' => $results, 'hasMore' => false]);
    }

    #[Route('/api/search/venues', name: 'admin_api_search_venues')]
    public function searchVenues(Request $request, FootballApiService $apiService): Response
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['results' => [], 'hasMore' => false]);
        }

        $data = $apiService->getCachedData('venues', [
            'search' => $query,
        ], 3600);

        $results = [];
        foreach ($data['response'] ?? [] as $item) {
            $results[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'photo' => $item['image'] ?? null,
                'extra' => $item['city'] ?? '',
            ];
        }

        return $this->json(['results' => $results, 'hasMore' => false]);
    }
}
