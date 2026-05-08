<?php

namespace Database\Seeders;

use App\Models\Genre;
use App\Models\Movie;
use Idei\Usim\Support\TranslationService;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $movies = [
            [
                'title' => 'The Shawshank Redemption',
                'genre' => 'genre.drama',
                'release_year' => 1994,
                'cast_members' => 'Tim Robbins, Morgan Freeman, Bob Gunton',
                'synopsis' => 'Two imprisoned men bond over years, finding redemption and hope through acts of decency.',
            ],
            [
                'title' => 'The Godfather',
                'genre' => 'genre.crime',
                'release_year' => 1972,
                'cast_members' => 'Marlon Brando, Al Pacino, James Caan',
                'synopsis' => 'The aging patriarch of a powerful crime family transfers control of his empire to his reluctant son.',
            ],
            [
                'title' => 'The Dark Knight',
                'genre' => 'genre.action',
                'release_year' => 2008,
                'cast_members' => 'Christian Bale, Heath Ledger, Aaron Eckhart',
                'synopsis' => 'Batman faces a chaotic criminal mastermind known as the Joker, pushing Gotham to its limits.',
            ],
            [
                'title' => 'Pulp Fiction',
                'genre' => 'genre.crime',
                'release_year' => 1994,
                'cast_members' => 'John Travolta, Uma Thurman, Samuel L. Jackson',
                'synopsis' => 'Interwoven stories of crime and redemption unfold in a stylized journey through Los Angeles underworld.',
            ],
            [
                'title' => 'Forrest Gump',
                'genre' => 'genre.drama',
                'release_year' => 1994,
                'cast_members' => 'Tom Hanks, Robin Wright, Gary Sinise',
                'synopsis' => 'A kind-hearted man witnesses and influences key moments in American history while pursuing true love.',
            ],
            [
                'title' => 'Inception',
                'genre' => 'genre.sci-fi',
                'release_year' => 2010,
                'cast_members' => 'Leonardo DiCaprio, Joseph Gordon-Levitt, Ellen Page',
                'synopsis' => 'A thief who steals secrets through dream-sharing technology is tasked with planting an idea into a target mind.',
            ],
            [
                'title' => 'The Matrix',
                'genre' => 'genre.sci-fi',
                'release_year' => 1999,
                'cast_members' => 'Keanu Reeves, Laurence Fishburne, Carrie-Anne Moss',
                'synopsis' => 'A hacker discovers reality is a simulation and joins a rebellion against the machines controlling humanity.',
            ],
            [
                'title' => 'Gladiator',
                'genre' => 'genre.action',
                'release_year' => 2000,
                'cast_members' => 'Russell Crowe, Joaquin Phoenix, Connie Nielsen',
                'synopsis' => 'A Roman general is betrayed and forced into slavery, then rises as a gladiator seeking justice.',
            ],
            [
                'title' => 'Interstellar',
                'genre' => 'genre.sci-fi',
                'release_year' => 2014,
                'cast_members' => 'Matthew McConaughey, Anne Hathaway, Jessica Chastain',
                'synopsis' => 'Explorers travel through a wormhole to find a new home for humanity as Earth becomes uninhabitable.',
            ],
            [
                'title' => 'The Lord of the Rings: The Fellowship of the Ring',
                'genre' => 'genre.fantasy',
                'release_year' => 2001,
                'cast_members' => 'Elijah Wood, Ian McKellen, Viggo Mortensen',
                'synopsis' => 'A hobbit and his companions begin a perilous quest to destroy a powerful ring before evil claims it.',
            ],
            [
                'title' => 'The Lord of the Rings: The Return of the King',
                'genre' => 'genre.fantasy',
                'release_year' => 2003,
                'cast_members' => 'Elijah Wood, Sean Astin, Ian McKellen',
                'synopsis' => 'Final battles decide the fate of Middle-earth as the ring-bearer nears Mount Doom.',
            ],
            [
                'title' => 'The Lion King',
                'genre' => 'genre.animation',
                'release_year' => 1994,
                'cast_members' => 'Matthew Broderick, Jeremy Irons, James Earl Jones',
                'synopsis' => 'A young lion prince must accept his destiny and reclaim his kingdom from a treacherous usurper.',
            ],
            [
                'title' => 'Titanic',
                'genre' => 'genre.romance',
                'release_year' => 1997,
                'cast_members' => 'Leonardo DiCaprio, Kate Winslet, Billy Zane',
                'synopsis' => 'A forbidden romance unfolds aboard the ill-fated RMS Titanic during its maiden voyage.',
            ],
            [
                'title' => 'Fight Club',
                'genre' => 'genre.drama',
                'release_year' => 1999,
                'cast_members' => 'Brad Pitt, Edward Norton, Helena Bonham Carter',
                'synopsis' => 'An insomniac office worker and a charismatic soapmaker form an underground fight club with dangerous consequences.',
            ],
            [
                'title' => 'Whiplash',
                'genre' => 'genre.drama',
                'release_year' => 2014,
                'cast_members' => 'Miles Teller, J.K. Simmons, Paul Reiser',
                'synopsis' => 'A driven jazz drummer endures brutal training under a ruthless instructor in pursuit of greatness.',
            ],
            [
                'title' => 'Parasite',
                'genre' => 'genre.thriller',
                'release_year' => 2019,
                'cast_members' => 'Song Kang-ho, Lee Sun-kyun, Cho Yeo-jeong',
                'synopsis' => 'A poor family infiltrates a wealthy household, setting in motion an escalating and unpredictable conflict.',
            ],
            [
                'title' => 'Spirited Away',
                'genre' => 'genre.animation',
                'release_year' => 2001,
                'cast_members' => 'Rumi Hiiragi, Miyu Irino, Mari Natsuki',
                'synopsis' => 'A young girl enters a magical spirit world and must find courage to save her parents and return home.',
            ],
        ];

        $translationService = app(TranslationService::class);

        foreach ($movies as $index => $data) {
            $genre = Genre::firstWhere('name', $data['genre']);

            if ($genre === null) {
                continue;
            }

            $key = 'movie.' . str()->slug($data['title']);
            $translationService->upsertFallbackValue($key, $data['title']);

            Movie::updateOrCreate(
                ['title' => $key],
                [
                    'genre_id' => $genre->id,
                    'image_url' => $this->fakeVerticalImageUrl($index + 1),
                    'release_year' => $data['release_year'],
                    'cast_members' => $data['cast_members'],
                    'synopsis' => $data['synopsis'],
                ]
            );
        }
    }

    private function fakeVerticalImageUrl(int $seed): string
    {
        return sprintf('https://picsum.photos/seed/movie-%d/1000/1500', $seed);
    }
}
