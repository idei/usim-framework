<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Screen;
use Idei\Usim\Components\LabelBuilder;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Components\UploaderBuilder;
use Idei\Usim\UIBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Uploader Demo Service
 *
 * Demuestra el uso del componente UploaderBuilder
 */
class UploaderDemo extends Screen
{
    protected UploaderBuilder $uploader_profile;
    protected UploaderBuilder $uploader_banner;
    protected UploaderBuilder $uploader_story;
    protected UploaderBuilder $uploader_images;
    protected UploaderBuilder $uploader_documents;
    protected LabelBuilder $lbl_result;
    protected $btn_confirm_profile;
    protected $btn_confirm_banner;
    protected $btn_confirm_story;
    protected $btn_confirm_images;
    protected $btn_confirm_documents;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.uploader_demo.title'))
            ->maxWidth('800px')
            ->centerHorizontal()
            ->plain()
            ->padding('30px');

        // Instrucciones
        $container->add(
            UIBuilder::label('lbl_instructions')
                ->text(t('screen.demo.uploader_demo.instruction'))
                ->style('info')
        );

        // Uploader de imagen única (perfil)
        $container->add(
            UIBuilder::label('lbl_profile_title')
                ->text(t('screen.demo.uploader_demo.profile.title'))
                ->style('primary')
        );

        $container->add(
            UIBuilder::uploader('uploader_profile')
                ->allowedTypes(['image/*'])
                ->label(t('screen.demo.uploader_demo.profile.label'))
                ->maxFiles(1)
                ->maxSize(2)
                ->aspect('1:1')
                ->size(2)
        );

        $container->add(
            UIBuilder::button('btn_confirm_profile')
                ->label(t('screen.demo.uploader_demo.profile.confirm'))
                ->style('success')
                ->action('process_profile')
        );

        // Separador
        $container->add(
            UIBuilder::label('lbl_separator1')
                ->text(t('screen.demo.uploader_demo.separator'))
                ->style('secondary')
        );

        // Uploader de banner (16:9)
        $container->add(
            UIBuilder::label('lbl_banner_title')
                ->text(t('screen.demo.uploader_demo.banner.title'))
                ->style('primary')
        );

        $container->add(
            UIBuilder::uploader('uploader_banner')
                ->allowedTypes(['image/*'])
                ->label(t('screen.demo.uploader_demo.banner.label'))
                ->maxFiles(1)
                ->maxSize(3)
                ->aspect('16:9')
                ->size(3)
        );

        $container->add(
            UIBuilder::button('btn_confirm_banner')
                ->label(t('screen.demo.uploader_demo.banner.confirm'))
                ->style('success')
                ->action('process_banner')
        );

        // Separador
        $container->add(
            UIBuilder::label('lbl_separator2')
                ->text(t('screen.demo.uploader_demo.separator'))
                ->style('secondary')
        );

        // Uploader de story (9:16)
        $container->add(
            UIBuilder::label('lbl_story_title')
                ->text(t('screen.demo.uploader_demo.story.title'))
                ->style('primary')
        );

        $container->add(
            UIBuilder::uploader('uploader_story')
                ->allowedTypes(['image/*'])
                ->label(t('screen.demo.uploader_demo.story.label'))
                ->maxFiles(1)
                ->maxSize(3)
                ->aspect('9:16')
                ->size(2)
        );

        $container->add(
            UIBuilder::button('btn_confirm_story')
                ->label(t('screen.demo.uploader_demo.story.confirm'))
                ->style('success')
                ->action('process_story')
        );

        // Separador
        $container->add(
            UIBuilder::label('lbl_separator3')
                ->text(t('screen.demo.uploader_demo.separator'))
                ->style('secondary')
        );

        // Uploader de imágenes
        $container->add(
            UIBuilder::label('lbl_images_title')
                ->text(t('screen.demo.uploader_demo.images.title'))
                ->style('primary')
        );

        $container->add(
            UIBuilder::uploader('uploader_images')
                ->images()
                ->label(t('screen.demo.uploader_demo.images.label'))
                ->maxFiles(3)
                ->maxSize(5)
        );

        $container->add(
            UIBuilder::button('btn_confirm_images')
                ->label(t('screen.demo.uploader_demo.images.confirm'))
                ->style('success')
                ->action('process_images')
        );

        // Separador
        $container->add(
            UIBuilder::label('lbl_separator4')
                ->text(t('screen.demo.uploader_demo.separator'))
                ->style('secondary')
        );

        // Uploader de documentos
        $container->add(
            UIBuilder::label('lbl_documents_title')
                ->text(t('screen.demo.uploader_demo.documents.title'))
                ->style('primary')
        );

        $container->add(
            UIBuilder::uploader('uploader_documents')
                ->documents()
                ->label(t('screen.demo.uploader_demo.documents.label'))
                ->maxFiles(2)
                ->maxSize(10)
        );

        $container->add(
            UIBuilder::button('btn_confirm_documents')
                ->label(t('screen.demo.uploader_demo.documents.confirm'))
                ->style('success')
                ->action('process_documents')
        );

        // Resultado
        $container->add(
            UIBuilder::label('lbl_result')
                ->text(t('screen.demo.uploader_demo.result.initial'))
                ->style('secondary')
        );
    }

    protected function postLoadUI(): void
    {
        $this->lbl_result
            ->text(t('screen.demo.uploader_demo.result.initial'))
            ->style('secondary');
    }

    /**
     * Procesar foto de perfil
     */
    public function onProcessProfile(array $params): void
    {
        // Obtener temp_ids del input hidden del uploader
        $tempIdsJson = $params['uploader_profile_temp_ids'] ?? '[]';
        $tempIds = json_decode($tempIdsJson, true) ?: [];

        if (empty($tempIds)) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.no_profile'))
                ->style('danger');
            return;
        }

        // Obtener archivo temporal
        $file = DB::table('temporary_uploads')
            ->where('id', $tempIds[0])
            ->first();

        if (!$file) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.temp_not_found'))
                ->style('danger');
            return;
        }

        // Mover archivo de temporal a final (carpeta por tipo)
        $finalPath = 'uploads/images/' . $file->stored_filename;
        Storage::move($file->path, $finalPath);

        // Limpiar temporal
        DB::table('temporary_uploads')->where('id', $file->id)->delete();

        // Mostrar resultado
        $sizeMB = round($file->size / 1024 / 1024, 2);
        $result = t('screen.demo.uploader_demo.profile.processed_header');
        $result .= t('screen.demo.uploader_demo.result.file_line_photo', [
            'name' => $file->original_filename,
            'size' => $sizeMB,
        ]);
        $result .= t('screen.demo.uploader_demo.result.path_line', ['path' => $finalPath]);

        $this->lbl_result
            ->text($result)
            ->style('success');

        // Limpiar uploader después de procesar
        $this->uiChanges()->add([
            'clear_uploaders' => [$this->uploader_profile->getId()]
        ]);
    }

    /**
     * Procesar banner
     */
    public function onProcessBanner(array $params): void
    {
        $tempIdsJson = $params['uploader_banner_temp_ids'] ?? '[]';
        $tempIds = json_decode($tempIdsJson, true) ?: [];

        if (empty($tempIds)) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.no_banner'))
                ->style('danger');
            return;
        }

        $file = DB::table('temporary_uploads')->where('id', $tempIds[0])->first();

        if (!$file) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.temp_not_found'))
                ->style('danger');
            return;
        }

        $finalPath = 'uploads/images/' . $file->stored_filename;
        Storage::move($file->path, $finalPath);
        DB::table('temporary_uploads')->where('id', $file->id)->delete();

        $sizeMB = round($file->size / 1024 / 1024, 2);
        $result = t('screen.demo.uploader_demo.banner.processed_header');
        $result .= t('screen.demo.uploader_demo.result.file_line_banner', [
            'name' => $file->original_filename,
            'size' => $sizeMB,
        ]);
        $result .= t('screen.demo.uploader_demo.result.path_line', ['path' => $finalPath]);

        $this->lbl_result
            ->text($result)
            ->style('success');

        $this->uiChanges()->add([
            'clear_uploaders' => [$this->uploader_banner->getId()]
        ]);
    }

    /**
     * Procesar story
     */
    public function onProcessStory(array $params): void
    {
        $tempIdsJson = $params['uploader_story_temp_ids'] ?? '[]';
        $tempIds = json_decode($tempIdsJson, true) ?: [];

        if (empty($tempIds)) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.no_story'))
                ->style('danger');
            return;
        }

        $file = DB::table('temporary_uploads')->where('id', $tempIds[0])->first();

        if (!$file) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.temp_not_found'))
                ->style('danger');
            return;
        }

        $finalPath = 'uploads/images/' . $file->stored_filename;
        Storage::move($file->path, $finalPath);
        DB::table('temporary_uploads')->where('id', $file->id)->delete();

        $sizeMB = round($file->size / 1024 / 1024, 2);
        $result = t('screen.demo.uploader_demo.story.processed_header');
        $result .= t('screen.demo.uploader_demo.result.file_line_story', [
            'name' => $file->original_filename,
            'size' => $sizeMB,
        ]);
        $result .= t('screen.demo.uploader_demo.result.path_line', ['path' => $finalPath]);

        $this->lbl_result
            ->text($result)
            ->style('success');

        $this->uiChanges()->add([
            'clear_uploaders' => [$this->uploader_story->getId()]
        ]);
    }

    /**
     * Procesar imágenes subidas
     */
    public function onProcessImages(array $params): void
    {
        // Obtener temp_ids del input hidden del uploader
        $tempIdsJson = $params['uploader_images_temp_ids'] ?? '[]';
        $tempIds = json_decode($tempIdsJson, true) ?: [];

        if (empty($tempIds)) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.no_images'))
                ->style('danger');
            return;
        }

        // Obtener archivos temporales
        $files = DB::table('temporary_uploads')
            ->whereIn('id', $tempIds)
            ->get();

        $processedFiles = [];

        foreach ($files as $temp) {
            // Mover archivo de temporal a final
            $finalPath = 'uploads/images/' . $temp->stored_filename;
            Storage::move($temp->path, $finalPath);

            // Aquí podrías guardar en BD si fuera necesario
            // DB::table('images')->insert([...]);

            $processedFiles[] = [
                'original' => $temp->original_filename,
                'size' => $temp->size,
                'path' => $finalPath,
            ];

            // Limpiar temporal
            DB::table('temporary_uploads')->where('id', $temp->id)->delete();
        }

        // Mostrar resultado
        $result = t('screen.demo.uploader_demo.images.processed_header');
        foreach ($processedFiles as $file) {
            $sizeMB = round($file['size'] / 1024 / 1024, 2);
            $result .= t('screen.demo.uploader_demo.result.file_line_photo', [
                'name' => $file['original'],
                'size' => $sizeMB,
            ]);
            $result .= t('screen.demo.uploader_demo.result.path_line_with_spacing', ['path' => $file['path']]);
        }

        $this->lbl_result
            ->text($result)
            ->style('success');

        // Limpiar uploader después de procesar
        $this->uiChanges()->add([
            'clear_uploaders' => [$this->uploader_images->getId()]
        ]);
    }

    /**
     * Procesar documentos subidos
     */
    public function onProcessDocuments(array $params): void
    {
        // Obtener temp_ids del input hidden del uploader
        $tempIdsJson = $params['uploader_documents_temp_ids'] ?? '[]';
        $tempIds = json_decode($tempIdsJson, true) ?: [];

        if (empty($tempIds)) {
            $this->lbl_result
                ->text(t('screen.demo.uploader_demo.errors.no_documents'))
                ->style('danger');
            return;
        }

        // Obtener archivos temporales
        $files = DB::table('temporary_uploads')
            ->whereIn('id', $tempIds)
            ->get();

        $processedFiles = [];

        foreach ($files as $temp) {
            // Mover archivo de temporal a final
            $finalPath = 'uploads/documents/' . $temp->stored_filename;
            Storage::move($temp->path, $finalPath);

            // Aquí podrías guardar en BD si fuera necesario
            // DB::table('documents')->insert([...]);

            $processedFiles[] = [
                'original' => $temp->original_filename,
                'type' => $temp->type,
                'size' => $temp->size,
                'path' => $finalPath,
            ];

            // Limpiar temporal
            DB::table('temporary_uploads')->where('id', $temp->id)->delete();
        }

        // Mostrar resultado
        $result = t('screen.demo.uploader_demo.documents.processed_header');
        foreach ($processedFiles as $file) {
            $sizeMB = round($file['size'] / 1024 / 1024, 2);
            $emoji = $this->getDocumentEmoji($file['original']);
            $result .= t('screen.demo.uploader_demo.result.file_line_document', [
                'emoji' => $emoji,
                'name' => $file['original'],
                'size' => $sizeMB,
            ]);
            $result .= t('screen.demo.uploader_demo.result.path_line_with_spacing', ['path' => $file['path']]);
        }

        $this->lbl_result
            ->text($result)
            ->style('success');

        // Limpiar uploader después de procesar
        $this->uiChanges()->add([
            'clear_uploaders' => [$this->uploader_documents->getId()]
        ]);
    }

    /**
     * Obtener emoji según tipo de documento
     */
    private function getDocumentEmoji(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => '📄',
            'doc', 'docx' => '📝',
            'xls', 'xlsx' => '📊',
            'ppt', 'pptx' => '📽️',
            default => '📎',
        };
    }
}
