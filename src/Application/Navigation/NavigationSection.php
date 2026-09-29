<?php

declare(strict_types=1);

namespace App\Application\Navigation;

enum NavigationSection: string
{
    case DASHBOARD = 'dashboard';
    case ACTIVITIES = 'activities';
    case GEAR = 'gear';
    case SEGMENTS = 'segments';
    case MONTHLY_STATS = 'monthly-stats';
    case EDDINGTON = 'eddington';
    case HEATMAP = 'heatmap';
    case BEST_EFFORTS = 'best-efforts';
    case MILESTONES = 'milestones';
    case REWIND = 'rewind';
    case CHALLENGES = 'challenges';
    case PHOTOS = 'photos';
}
