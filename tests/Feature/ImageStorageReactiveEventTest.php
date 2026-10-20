<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Image;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Tests\TestCase;

class ImageStorageReactiveTestScreen extends Screen
{
    public const ROUTE_PATH = '/image-storage-reactive-test';

    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    protected Image $avatar;

    protected Button $change_avatar_btn;

    protected Button $rebuild_btn;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->avatar = UI::image('avatar')
            ->src('avatar_1.png')
            ->storage('uploads/avatars');

        $this->change_avatar_btn = UI::button('change_avatar_btn')
            ->action('change_avatar', ['filename' => 'avatar_2.png']);

        $this->rebuild_btn = UI::button('rebuild_btn')
            ->action('rebuild_collection');

        $container->add($this->avatar);
        $container->add($this->change_avatar_btn);
        $container->add($this->rebuild_btn);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onChangeAvatar(array $params = []): void
    {
        $filename = (string) ($params['filename'] ?? 'avatar_2.png');
        $this->avatar->src($filename);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onRebuildCollection(array $params = []): void
    {
        // Rebuild/add dynamic image during an event (e.g. onDeletePost / collection update)
        $dynamicImage = UI::image('post_cover')
            ->src('116ad8cd-6001-4b3c-8323-cf5f76a99341.png')
            ->storage('uploads/images');

        $this->getContainer()?->add($dynamicImage);
    }
}

it('preserves canonical resolved url for image with storage during reactive UI events', function () {
    /** @var TestCase $this */
    $ui = uiScenario($this, ImageStorageReactiveTestScreen::class, ['reset' => true]);

    $avatarComp = $ui->component('avatar');
    $avatarId = $avatarComp->id();

    // Verify initial render resolved relative filename to canonical storage URL
    $expectedInitialUrl = url('/files/uploads/avatars/avatar_1.png');
    $avatarComp->expect('src')->toBe($expectedInitialUrl);

    // Trigger reactive event that mutates the image source
    $response = $ui->action('change_avatar_btn', 'change_avatar', [
        'filename' => 'avatar_2.png',
    ]);
    $response->assertOk();

    // Verify the diff emitted by the reactive event contains the canonical resolved URL, NOT the raw filename
    $expectedUpdatedUrl = url('/files/uploads/avatars/avatar_2.png');
    $diffSrc = $response->json("{$avatarId}.src");

    expect($diffSrc)->toBe($expectedUpdatedUrl);
    expect($diffSrc)->not->toBe('avatar_2.png');

    // Verify internal storage path metadata is never emitted in diff payload
    expect($response->json("{$avatarId}"))->not->toHaveKey('storage_path');
    expect($response->json("{$avatarId}"))->not->toHaveKey('_storage_path');
});

it('preserves canonical resolved url when image components are created during reactive collection rebuilds', function () {
    /** @var TestCase $this */
    $ui = uiScenario($this, ImageStorageReactiveTestScreen::class, ['reset' => true]);

    // Trigger reactive event that rebuilds or dynamically appends an image with relative storage path
    $response = $ui->action('rebuild_btn', 'rebuild_collection');
    $response->assertOk();

    $expectedUrl = url('/files/uploads/images/116ad8cd-6001-4b3c-8323-cf5f76a99341.png');

    // Find the newly added dynamic image component in the diff response
    $rawPostImageFilename = '116ad8cd-6001-4b3c-8323-cf5f76a99341.png';
    $foundDynamicImageDiff = null;

    foreach ($response->json() as $id => $componentDiff) {
        if (is_array($componentDiff) && ($componentDiff['name'] ?? null) === 'post_cover') {
            $foundDynamicImageDiff = $componentDiff;
            break;
        }
    }

    expect($foundDynamicImageDiff)->not->toBeNull();
    expect($foundDynamicImageDiff['type'])->toBe('image');
    expect($foundDynamicImageDiff['src'])->toBe($expectedUrl);
    expect($foundDynamicImageDiff['src'])->not->toBe($rawPostImageFilename);
    expect($foundDynamicImageDiff)->not->toHaveKey('storage_path');
    expect($foundDynamicImageDiff)->not->toHaveKey('_storage_path');
});

