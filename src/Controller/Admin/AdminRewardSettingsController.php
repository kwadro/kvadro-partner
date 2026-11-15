<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin_9fg!lp/settings')]
class AdminRewardSettingsController extends AbstractController
{
    #[Route('/rewards', name: 'app_admin_settings_rewards')]
    public function rewards(): Response
    {
        $example = $this->buildExampleBreakdown(10.0, 5.0, 2.0, 1000.0);

        return $this->render('admin/reward_settings.html.twig', [
            'active_menu' => 'settings',
            'example' => $example,
        ]);
    }

    /**
     * @return array
     */
    private function buildExampleBreakdown(
        float $ownPercent,
        float $level1Percent,
        float $level2Percent,
        float $orderAmount,
    ): array {
        $own = round($orderAmount * $ownPercent / 100, 2);
        $level1 = round($orderAmount * $level1Percent / 100, 2);
        $level2 = round($orderAmount * $level2Percent / 100, 2);

        return [
            'orderAmount' => number_format($orderAmount, 2, '.', ''),
            'own' => number_format($own, 2, '.', ''),
            'level1' => number_format($level1, 2, '.', ''),
            'level2' => number_format($level2, 2, '.', ''),
            'total' => number_format($own + $level1 + $level2, 2, '.', ''),
            'ownPercent' => number_format($ownPercent, 2, '.', ''),
            'level1Percent' => number_format($level1Percent, 2, '.', ''),
            'level2Percent' => number_format($level2Percent, 2, '.', ''),
        ];
    }
}
