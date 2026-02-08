<?php

namespace App\Controller;

use App\Service\api\FootballApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\NewsRepository;
use Symfony\Component\HttpFoundation\Request;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        FootballApiService $apiService, 
        NewsRepository $newsRepository,
        Request $request
    ): Response
        {

        $dateString = $request->query->get('date', date('Y-m-d'));
        $orden = $request->query->get('orden', 'tops'); 

        $fixturesData = $apiService->getFixturesByDate($dateString);
        $partidos = $fixturesData['response'] ?? [];
        $prioridadLigas = [
            'La Liga|Spain' => 1,
            'Copa del Rey|Spain' => 2,
            'Super Cup|Spain' => 3,
            'Segunda División|Spain' => 4,
            'World Cup|World' => 5,
            'UEFA Champions League|World' => 6,
            'Euro Championship|World' => 7,
            'Copa America|South America' => 8,
            'FIFA Club World Cup|World' => 9,
            'Copa Libertadores|South America' => 10,
            'UEFA Europa League|World' => 11,
            'CONCACAF Champions League|North America' => 12,
            'Africa Cup of Nations|Africa' => 13,
            'AFC Champions League|Asia' => 14,
            'CAF Champions League|Africa' => 15,
            'Copa Sudamericana|South America' => 16,
            'UEFA Super Cup|World' => 17,
            'UEFA Europa Conference League|World' => 18,
            'AFC Asian Cup|Asia' => 19,
            'CONCACAF Gold Cup|North America' => 20,
            'Premier League|England' => 21,
            'FA Cup|England' => 22,
            'EFL Cup|England' => 23,
            'Community Shield|England' => 24,
            'Serie A|Italy' => 25,
            'Coppa Italia|Italy' => 26,
            'Supercoppa Italiana|Italy' => 27,
            'Bundesliga|Germany' => 28,
            'DFB Pokal|Germany' => 29,
            'Ligue 1|France' => 30,
            'Coupe de France|France' => 31,
            'Primeira Liga|Portugal' => 32,
            'Taca de Portugal|Portugal' => 33,
            'Eredivisie|Netherlands' => 34,
            'KNVB Beker|Netherlands' => 35,
            'Pro League|Belgium' => 36,
            'Jupiler Pro League|Belgium' => 37,
            'Super Lig|Turkey' => 38,
            'Premiership|Scotland' => 39,
            'Scottish Cup|Scotland' => 40,
            'Serie A|Brazil' => 41,
            'Copa Do Brasil|Brazil' => 42,
            'Liga Profesional Argentina|Argentina' => 43,
            'Copa Argentina|Argentina' => 44,
            'Liga MX|Mexico' => 45,
            'MLS|USA' => 46,
            'Saudi Pro League|Saudi-Arabia' => 47,
            'Super League|Switzerland' => 48,
            'Bundesliga|Austria' => 49,
            'Super League 1|Greece' => 50,
            'J1 League|Japan' => 51,
            'K League 1|Korea' => 52,
            'Chinese Super League|China' => 53,
        ];
        $prioridadPorPais = [
            'Spain' => 1,
            'World' => 2,
            'England' => 3,
            'Italy' => 4,
            'Germany' => 5,
            'France' => 6,
            'Portugal' => 7,
            'Netherlands' => 8,
            'Belgium' => 9,
            'Turkey' => 10,
            'Scotland' => 11,
            'Brazil' => 12,
            'Argentina' => 13,
            'Mexico' => 14,
            'USA' => 15,
            'Canada' => 16,
            'Saudi-Arabia' => 17,
            'Switzerland' => 18,
            'Austria' => 19,
            'Greece' => 20,
            'Denmark' => 21,
            'Czech-Republic' => 22,
            'Croatia' => 23,
            'Russia' => 24,
            'Ukraine' => 25,
            'Japan' => 26,
            'South-Korea' => 27,
            'China' => 28,
        ];
        $prioridadLigaDentroPais = [
            'Copa del Rey' => 1, 'Super Cup' => 2,
            'La Liga' => 10, 'Segunda División' => 20,
            'Primera División RFEF' => 30, 'Segunda División RFEF' => 35, 'Tercera División RFEF' => 40,
            'World Cup' => 1, 'FIFA World Cup' => 1,
            'UEFA Champions League' => 2,
            'Euro Championship' => 3,
            'Copa America' => 4,
            'FIFA Club World Cup' => 5, 'Club World Cup' => 5,
            'Copa Libertadores' => 6,
            'UEFA Europa League' => 7,
            'CONCACAF Champions League' => 8, 'Concacaf Champions Cup' => 8,
            'Africa Cup of Nations' => 9,
            'AFC Champions League Elite' => 10, 'AFC Champions League' => 10,
            'CAF Champions League' => 11,
            'Copa Sudamericana' => 12,
            'UEFA Super Cup' => 13,
            'UEFA Europa Conference League' => 14,
            'AFC Asian Cup' => 15,
            'CONCACAF Gold Cup' => 16, 'Gold Cup' => 16,
            'Friendlies Clubs' => 80,
            'Friendlies' => 81, 'Club Friendlies' => 80,
            'FA Cup' => 1, 'EFL Cup' => 2, 'Community Shield' => 3, 'Premier League' => 10, 'Championship' => 20,
            'Coppa Italia' => 1, 'Supercoppa Italiana' => 2, 'Serie A' => 10, 'Serie B' => 20,
            'DFB Pokal' => 1, 'Bundesliga' => 10, '2. Bundesliga' => 20,
            'Coupe de France' => 1, 'Ligue 1' => 10, 'Ligue 2' => 20,
            'Taca de Portugal' => 1, 'Primeira Liga' => 10,
            'KNVB Beker' => 1, 'Eredivisie' => 10,
            'Copa Do Brasil' => 1, 'Copa Argentina' => 1,
            'Liga MX' => 10, 'MLS' => 10,
        ];
        $calcularPrioridadRFEF = function($nombre) {
            if (preg_match('/Primera División RFEF.*Group\s*(\d+)/i', $nombre, $matches)) {
                return 30 + (int)$matches[1] * 0.01; 
            }
            if (preg_match('/Segunda División RFEF.*Group\s*(\d+)/i', $nombre, $matches)) {
                return 35 + (int)$matches[1] * 0.01;
            }
            if (preg_match('/Tercera División RFEF.*Group\s*(\d+)/i', $nombre, $matches)) {
                return 40 + (int)$matches[1] * 0.01;
            }
            return null;
        };
        $partidosPorLiga = [];
        foreach ($partidos as $partido) {
            $nombreLiga = $partido['league']['name'] ?? 'Otras Ligas';
            $paisLiga = $partido['league']['country'] ?? 'World';
            $claveOrden = $nombreLiga . '|' . $paisLiga; 
            
            if (!isset($partidosPorLiga[$claveOrden])) {
                $partidosPorLiga[$claveOrden] = [
                    'nombre' => $nombreLiga,
                    'logo' => $partido['league']['logo'] ?? '',
                    'country' => $paisLiga,
                    'partidos' => [],
                ];
            }
            $partidosPorLiga[$claveOrden]['partidos'][] = $partido;
        }
        $partidosAgrupadosPorPais = [];
        if ($orden === 'pais') {
            uasort($partidosPorLiga, function($a, $b) use ($prioridadPorPais, $prioridadLigaDentroPais, $calcularPrioridadRFEF) {
                $prioridadPaisA = $prioridadPorPais[$a['country']] ?? 999;
                $prioridadPaisB = $prioridadPorPais[$b['country']] ?? 999;
                
                if ($prioridadPaisA !== $prioridadPaisB) {
                    return $prioridadPaisA - $prioridadPaisB;
                }
                $esFemeninaA = (stripos($a['nombre'], 'Women') !== false || stripos($a['nombre'], 'Femenin') !== false) ? 100 : 0;
                $esFemeninaB = (stripos($b['nombre'], 'Women') !== false || stripos($b['nombre'], 'Femenin') !== false) ? 100 : 0;
                
                if ($esFemeninaA !== $esFemeninaB) {
                    return $esFemeninaA - $esFemeninaB;
                }
                $rfefA = $calcularPrioridadRFEF($a['nombre']);
                $rfefB = $calcularPrioridadRFEF($b['nombre']);
                
                if ($rfefA !== null && $rfefB !== null) {
                    return $rfefA <=> $rfefB;
                } elseif ($rfefA !== null) {
                    $baseB = $prioridadLigaDentroPais[$b['nombre']] ?? 50;
                    return $rfefA <=> $baseB;
                } elseif ($rfefB !== null) {
                    $baseA = $prioridadLigaDentroPais[$a['nombre']] ?? 50;
                    return $baseA <=> $rfefB;
                }
                $prioridadLigaA = $prioridadLigaDentroPais[$a['nombre']] ?? 50;
                $prioridadLigaB = $prioridadLigaDentroPais[$b['nombre']] ?? 50;
                
                return $prioridadLigaA <=> $prioridadLigaB;
            });
            foreach ($partidosPorLiga as $claveOrden => $dataLiga) {
                $pais = $dataLiga['country'];
                if (!isset($partidosAgrupadosPorPais[$pais])) {
                    $partidosAgrupadosPorPais[$pais] = [
                        'pais' => $pais,
                        'prioridad' => $prioridadPorPais[$pais] ?? 999,
                        'ligas' => [],
                    ];
                }
                $partidosAgrupadosPorPais[$pais]['ligas'][] = $dataLiga;
            }
            uasort($partidosAgrupadosPorPais, function($a, $b) {
                return $a['prioridad'] - $b['prioridad'];
            });
        } else {
            uksort($partidosPorLiga, function($a, $b) use ($prioridadLigas) {
                $prioridadA = $prioridadLigas[$a] ?? 999;
                $prioridadB = $prioridadLigas[$b] ?? 999;
                return $prioridadA - $prioridadB;
            });
        }

        $noticias = $newsRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('n');
        $currentSeason = ($currentMonth < 8) ? $currentYear - 1 : $currentYear;
        $fichajes = $apiService->getTransfersByLeague(140, $currentSeason, 10);
        $lesiones = $apiService->getInjuriesByLeague(140, $currentSeason, 10);

        return $this->render('home/index.html.twig', [
            'partidosPorLiga' => $partidosPorLiga,
            'partidosPorPais' => $partidosAgrupadosPorPais,
            'noticias' => $noticias,
            'fichajes' => $fichajes,
            'lesiones' => $lesiones,
            'fechaActual' => $dateString,
            'ordenActual' => $orden,
        ]);
    }
}