<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Components\CalendarBuilder;

class CalendarDemo extends AbstractUIService
{
    protected CalendarBuilder $academic_calendar;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->maxWidth('600px')
            ->centerHorizontal()
            ->shadow(0)
            ->padding('30px');

        $this->academic_calendar = UIBuilder::calendar('academic_calendar')
            ->year(2026)
            ->month(date('n'))
            ->showSaturdayInfo(false)
            ->showSundayInfo(false)
            ->cellSize('60px')
            ->eventBorderRadius('50%')
            ->numberStyle([
                'font_size' => '13px',
                'background_color' => '#ffffff',
                'color' => '#333333',
                'box_shadow' => 'none'
            ])
            ->borderRadius('2px');

        $this->onMonthChanged([
            'year' => 2026,
            'month' => date('n')
        ]);

        $container->add($this->academic_calendar);
    }

    /**
     * Handle month change event
     *
     * @param array $params Contains 'year' and 'month'
     */
    public function onMonthChanged(array $params): void
    {
        $year = $params['year'];
        $month = $params['month'];
        $monthEvents = CalendarioAcadémico::getMonthEvents($year, $month);
        $this->academic_calendar->events($monthEvents);
    }
}
