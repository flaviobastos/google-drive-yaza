<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Yaza\LaravelGoogleDriveStorage\GoogleDriveAdapter;
use ZipArchive;
use Illuminate\Support\Facades\File;

/**
 * Controller para gerenciamento de arquivos no Google Drive usando yaza/laravel-google-drive-storage
 */
class GoogleController extends Controller
{
    /**
     * Lista todos os arquivos na pasta raiz do Google Drive
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        // Pasta atual (raiz por padrão)
        $currentFolder = $request->get('folder', '');

        // Normaliza o currentFolder para garantir comportamento consistente
        if ($currentFolder === '') {
            $currentFolder = '/';
        }

        /** @var GoogleDriveAdapter $adapter */
        $adapter = $disk->getAdapter();

        $folders = [];
        $files = [];

        try {
            // Lista todos os arquivos e diretórios
            $contents = $disk->listContents($currentFolder, false);

            foreach ($contents as $item) {
                $meta = $adapter->getMetadata($item->path());

                $itemData = [
                    'name' => $meta->path(),
                    'basename' => basename($meta->path()),
                    'size' => $item->isFile() ? $meta->fileSize() : 0,
                    'id' => $meta->extraMetadata()['id'] ?? null,
                    'last_modified' => $meta->lastModified(),
                ];

                if ($item->isDir()) {
                    $folders[] = $itemData;
                } else {
                    $files[] = $itemData;
                }
            }
        } catch (\Exception $e) {
            // Se ocorrer um erro, podemos registrá-lo e continuar com arrays vazios
            // Log::error('Erro ao listar arquivos: ' . $e->getMessage());
            // Ou exibir uma mensagem para o usuário:
            session()->flash('error', 'Erro ao listar arquivos: ' . $e->getMessage());
        }

        // Obtém lista de todas as pastas para o seletor de "mover", excluindo a pasta atual
        $allFolders = $this->getAllFolders($disk, $currentFolder);

        return view('index', compact('folders', 'files', 'currentFolder', 'allFolders'));
    }

    /**
     * Obtém todas as pastas do Google Drive
     *
     * @param FilesystemAdapter $disk O disco do Google Drive
     * @param string $currentFolder Pasta atual a ser excluída da lista (opcional)
     * @return array Lista de pastas disponíveis
     */
    private function getAllFolders(FilesystemAdapter $disk, string $currentFolder = ''): array
    {
        $allFolders = [];

        // Normaliza o currentFolder vazio ou '/' para identificar consistentemente a pasta raiz
        $isRootFolder = $currentFolder === '' || $currentFolder === '/';

        try {
            $contents = $disk->listContents('', true);

            foreach ($contents as $item) {
                if ($item->isDir()) {
                    $folderPath = $item->path();
                    // Exclui o diretório atual da lista
                    if ($folderPath !== $currentFolder) {
                        $allFolders[] = [
                            'path' => $folderPath,
                            'name' => $folderPath
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Registre o erro se necessário
            // Log::error('Erro ao listar pastas: ' . $e->getMessage());
        }

        // Adiciona a pasta raiz, exceto se estiver na pasta raiz
        if (!$isRootFolder) {
            array_unshift($allFolders, ['path' => '/', 'name' => 'Raiz']);
        }

        return $allFolders;
    }

    /**
     * Baixa um arquivo do Google Drive
     *
     * @param string $path Caminho do arquivo no Google Drive
     * @return StreamedResponse|\Illuminate\Http\Response
     */
    public function download(string $path)
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            if (!$disk->exists($path)) {
                abort(404, 'Arquivo não encontrado.');
            }

            return new StreamedResponse(function () use ($disk, $path) {
                $stream = $disk->readStream($path);
                fpassthru($stream);
            }, 200, [
                'Content-Type' => $disk->mimeType($path),
                'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
            ]);
        } catch (\Exception $e) {
            abort(500, 'Erro ao baixar arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Renomeia um arquivo no Google Drive
     *
     * @param Request $request Deve conter 'old_path' e 'new_name'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rename(Request $request)
    {
        $request->validate([
            'old_path' => 'required|string',
            'new_name' => 'required|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $oldPath = $request->input('old_path');
            $oldExtension = pathinfo($oldPath, PATHINFO_EXTENSION);

            // Adiciona a extensão original ao novo nome se não estiver presente
            $newName = $request->input('new_name');
            if ($oldExtension && !str_ends_with(strtolower($newName), '.' . strtolower($oldExtension))) {
                $newName .= '.' . $oldExtension;
            }

            $newPath = dirname($oldPath) . '/' . $newName;

            if (!$disk->exists($oldPath)) {
                return back()->with('error', 'Arquivo original não encontrado.');
            }

            $disk->move($oldPath, $newPath);

            return back()->with('success', 'Arquivo renomeado com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao renomear arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Move um arquivo para outro diretório no Google Drive
     *
     * @param Request $request Deve conter 'file_path' e 'new_folder'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function move(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
            'new_folder' => 'required|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $filePath = $request->input('file_path');
            $fileName = basename($filePath);
            $newPath = trim($request->input('new_folder'), '/') . '/' . $fileName;

            if (!$disk->exists($filePath)) {
                return back()->with('error', 'Arquivo não encontrado.');
            }

            // Verifica se o destino já existe um arquivo com o mesmo nome
            if ($disk->exists($newPath)) {
                return back()->with('error', 'Já existe um arquivo com o mesmo nome no destino.');
            }

            $disk->move($filePath, $newPath);

            return back()->with('success', 'Arquivo movido com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao mover arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Exclui um arquivo do Google Drive
     *
     * @param Request $request Deve conter 'file_path'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request)
    {
        $request->validate([
            'file_path' => 'required|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $filePath = $request->input('file_path');

            if (!$disk->exists($filePath)) {
                return back()->with('error', 'Arquivo não encontrado.');
            }

            $disk->delete($filePath);

            return back()->with('success', 'Arquivo excluído com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao excluir arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Cria uma nova pasta no Google Drive
     *
     * @param Request $request Deve conter 'folder_name' e 'current_folder'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createFolder(Request $request)
    {
        $request->validate([
            'folder_name' => 'required|string|max:255',
            'current_folder' => 'nullable|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        $folderName = trim($request->input('folder_name'));
        $currentFolder = trim($request->input('current_folder', ''));

        // Caminho completo para a nova pasta
        $newFolderPath = !empty($currentFolder)
            ? rtrim($currentFolder, '/') . '/' . $folderName
            : $folderName;

        try {
            // Verifica se a pasta já existe
            if ($disk->directoryExists($newFolderPath)) {
                return back()->with('error', "A pasta '{$folderName}' já existe neste diretório.");
            }

            // Criar pasta no Google Drive
            $disk->makeDirectory($newFolderPath);

            return back()->with('success', "Pasta '{$folderName}' criada com sucesso!");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao criar pasta: ' . $e->getMessage());
        }
    }

    /**
     * Faz upload de um arquivo para o Google Drive
     *
     * @param Request $request Deve conter 'file' e 'current_folder'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // máximo de 10MB
            'current_folder' => 'nullable|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $currentFolder = trim($request->input('current_folder', ''));

            // Caminho completo para o novo arquivo
            $newFilePath = !empty($currentFolder)
                ? rtrim($currentFolder, '/') . '/' . $fileName
                : $fileName;

            // Verifica se já existe um arquivo com o mesmo nome
            if ($disk->exists($newFilePath)) {
                return back()->with('error', 'Já existe um arquivo com este nome no diretório atual.');
            }

            // Faz upload do arquivo para o Google Drive
            $stream = fopen($file->getRealPath(), 'r+');
            $disk->writeStream($newFilePath, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            return back()->with('success', "Arquivo '{$fileName}' enviado com sucesso!");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao fazer upload do arquivo: ' . $e->getMessage());
        }
    }

    /**
     * Renomeia uma pasta no Google Drive
     *
     * @param Request $request Deve conter 'old_path' e 'new_name'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function renameFolder(Request $request)
    {
        $request->validate([
            'old_path' => 'required|string',
            'new_name' => 'required|string|max:255',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $oldPath = $request->input('old_path');
            $newName = trim($request->input('new_name'));
            $parentDir = dirname($oldPath);
            $newPath = ($parentDir === '.') ? $newName : $parentDir . '/' . $newName;

            if (!$disk->directoryExists($oldPath)) {
                return back()->with('error', 'Pasta original não encontrada.');
            }

            // Verifica se já existe uma pasta com o mesmo nome no destino
            if ($disk->directoryExists($newPath)) {
                return back()->with('error', 'Já existe uma pasta com este nome no mesmo diretório.');
            }

            // Lista todos os arquivos e subpastas dentro da pasta antiga recursivamente
            $contents = $disk->listContents($oldPath, true);
            $items = iterator_to_array($contents);

            // Primeiro, cria a pasta com o novo nome
            $disk->makeDirectory($newPath);

            // Depois move os conteúdos
            foreach ($items as $item) {
                $relativePath = substr($item->path(), strlen($oldPath) + 1);
                $newItemPath = $newPath . '/' . $relativePath;

                if ($item->isDir()) {
                    $disk->makeDirectory($newItemPath);
                } else {
                    $disk->copy($item->path(), $newItemPath);
                    $disk->delete($item->path());
                }
            }

            // Por fim, remove a pasta antiga
            $disk->deleteDirectory($oldPath);

            return back()->with('success', 'Pasta renomeada com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao renomear pasta: ' . $e->getMessage());
        }
    }

    /**
     * Exclui uma pasta e todo o seu conteúdo do Google Drive
     *
     * @param Request $request Deve conter 'folder_path'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteFolder(Request $request)
    {
        $request->validate([
            'folder_path' => 'required|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $folderPath = $request->input('folder_path');

            if (!$disk->directoryExists($folderPath)) {
                return back()->with('error', 'Pasta não encontrada.');
            }

            // Exclui a pasta e todo seu conteúdo
            $disk->deleteDirectory($folderPath);

            return back()->with('success', 'Pasta excluída com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao excluir pasta: ' . $e->getMessage());
        }
    }

    /**
     * Baixa uma pasta como arquivo ZIP com todo seu conteúdo
     *
     * @param string $path Caminho da pasta no Google Drive
     * @return StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadFolder(string $path)
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            if (!$disk->directoryExists($path)) {
                abort(404, 'Pasta não encontrada.');
            }

            // Nome do arquivo ZIP baseado no nome da pasta
            $folderName = basename($path);
            $zipFileName = $folderName . '.zip';

            // Caminho temporário para o ZIP
            $tempZipPath = storage_path('app/temp/' . $zipFileName);

            // Cria o diretório temporário se não existir
            if (!File::exists(storage_path('app/temp'))) {
                File::makeDirectory(storage_path('app/temp'), 0755, true);
            }

            // Remove o arquivo ZIP temporário se já existir
            if (File::exists($tempZipPath)) {
                File::delete($tempZipPath);
            }

            // Cria um novo arquivo ZIP
            $zip = new ZipArchive();
            if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Não foi possível criar o arquivo ZIP');
            }

            // Lista todos os arquivos e subpastas dentro da pasta recursivamente
            $contents = $disk->listContents($path, true);

            foreach ($contents as $item) {
                // Pula se for um diretório
                if ($item->isDir()) {
                    continue;
                }

                // Caminho relativo dentro do ZIP
                $relativePath = substr($item->path(), strlen($path) + 1);

                // Lê o conteúdo do arquivo
                $content = $disk->get($item->path());

                // Adiciona o arquivo ao ZIP
                $zip->addFromString($relativePath, $content);
            }

            // Fecha o arquivo ZIP
            $zip->close();

            // Retorna o arquivo ZIP como download
            $response = response()->download($tempZipPath, $zipFileName, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $zipFileName . '"',
            ])->deleteFileAfterSend(true);

            return $response;
        } catch (\Exception $e) {
            abort(500, 'Erro ao criar o download da pasta: ' . $e->getMessage());
        }
    }

    /**
     * Move uma pasta para outro diretório no Google Drive
     *
     * @param Request $request Deve conter 'folder_path' e 'new_folder'
     * @return \Illuminate\Http\RedirectResponse
     */
    public function moveFolder(Request $request)
    {
        $request->validate([
            'folder_path' => 'required|string',
            'new_folder' => 'required|string',
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('google');

        try {
            $folderPath = $request->input('folder_path');
            $folderName = basename($folderPath);

            // Não permitir mover uma pasta para dentro dela mesma ou subpastas
            $newFolder = trim($request->input('new_folder'), '/');
            if (empty($newFolder)) {
                $newFolder = '/';
            }

            // Verifica se está tentando mover para a própria pasta ou subfolder
            if ($newFolder === $folderPath || str_starts_with($newFolder, $folderPath . '/')) {
                return back()->with('error', 'Não é possível mover uma pasta para dentro dela mesma ou para uma de suas subpastas.');
            }

            // Define o novo caminho completo
            $newPath = ($newFolder === '/' ? '' : $newFolder) . '/' . $folderName;
            $newPath = ltrim($newPath, '/');

            if (!$disk->directoryExists($folderPath)) {
                return back()->with('error', 'Pasta não encontrada.');
            }

            // Verifica se já existe uma pasta com o mesmo nome no destino
            if ($disk->directoryExists($newPath)) {
                return back()->with('error', 'Já existe uma pasta com o mesmo nome no destino.');
            }

            // Lista todos os arquivos e subpastas dentro da pasta recursivamente
            $contents = $disk->listContents($folderPath, true);
            $items = iterator_to_array($contents);

            // Primeiro, cria a pasta com o novo caminho
            $disk->makeDirectory($newPath);

            // Depois move os conteúdos
            foreach ($items as $item) {
                $relativePath = substr($item['path'], strlen($folderPath) + 1);

                if ($item['type'] === 'file') {
                    // Move o arquivo
                    $sourceFile = $item['path'];
                    $targetFile = $newPath . '/' . $relativePath;

                    $stream = $disk->readStream($sourceFile);
                    $disk->writeStream($targetFile, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    // Exclui o arquivo original
                    $disk->delete($sourceFile);
                } elseif ($item['type'] === 'dir') {
                    // Cria o diretório no novo local
                    $disk->makeDirectory($newPath . '/' . $relativePath);
                }
            }

            // Por fim, remove a pasta original
            $disk->deleteDirectory($folderPath);

            return back()->with('success', 'Pasta movida com sucesso!');
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao mover pasta: ' . $e->getMessage());
        }
    }
}
