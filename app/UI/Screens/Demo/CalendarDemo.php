<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Calendar;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class CalendarDemo extends Screen
{
    protected Calendar $academic_calendar;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->maxWidth(Size::px(600))
            ->centerHorizontal()
            ->plain()
            ->padding(Spacing::px(30));

        $this->academic_calendar = UI::calendar('academic_calendar')
            ->year(2026)
            ->month((int) date('n'))
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
            'month' => (int) date('n')
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
