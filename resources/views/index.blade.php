<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Meu Google Drive</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Estilos do menu de contexto */
        .context-menu-item {
            transition: background-color 0.15s ease;
        }

        .context-menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .context-menu {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
        }

        /* Fix para botões flutuantes */
        button {
            position: relative;
        }

        /* Garantir que os modais sejam ocultados corretamente */
        .fixed.hidden {
            display: none !important;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'gdrive-blue': '#4285F4',
                        'gdrive-red': '#DB4437',
                        'gdrive-yellow': '#F4B400',
                        'gdrive-green': '#0F9D58',
                        'dark-bg': '#1a1a1a',
                        'dark-surface': '#242424',
                        'dark-border': '#333333',
                        'dark-hover': '#2d2d2d',
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-dark-bg text-gray-200 min-h-screen font-sans antialiased" x-data="driveApp()" x-cloak>

    <div x-show="isLoading" x-transition.opacity.duration.1000ms
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 backdrop-blur-sm">
        <div class="flex flex-col items-center justify-center">
            <div class="spinner w-12 h-12 border-4 border-gray-300 border-t-gdrive-blue rounded-full animate-spin">
            </div>
            <p class="mt-4 text-white text-sm">Carregando...</p>
        </div>
    </div>

    @livewire('loading')

    <div class="container flex flex-col items-center justify-center mx-auto px-4 py-8">
        <!-- Header -->
        <header class="flex items-center justify-between w-full pb-6 border-b border-dark-border mb-8">
            <div class="flex flex-row items-center justify-center w-fit space-x-2">
                <a href="{{ route('arquivos.index') }}" class="flex items-center">
                    <i class="fab fa-google-drive text-gdrive-blue text-3xl mr-2"></i>
                    <h1 class="text-2xl font-medium">Meu Google Drive</h1>
                </a>
            </div>

            <div class="w-full max-w-md mx-4">
                <div class="relative">
                    <input type="text" id="search-input" placeholder="Pesquisar no Drive" x-model="searchTerm"
                        @input="filterFiles()"
                        class="w-full bg-dark-surface border border-dark-border rounded-lg py-2 px-4 focus:outline-none focus:ring-2 focus:ring-gdrive-blue text-gray-200">
                    <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                </div>
            </div>
        </header>

        <!-- Mensagens flash -->
        @if (session('success'))
            <div class="bg-gdrive-green/15 border border-gdrive-green/30 text-white px-4 py-3 rounded relative mb-6"
                role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-gdrive-red/15 border border-gdrive-red/30 text-white px-4 py-3 rounded relative mb-6"
                role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-gdrive-red/15 border border-gdrive-red/30 text-white px-4 py-3 rounded relative mb-6"
                role="alert">
                @foreach ($errors->all() as $error)
                    <span class="block sm:inline">{{ $error }}</span>
                @endforeach
            </div>
        @endif

        <!-- Upload Button -->
        <div class="mb-4 w-full flex justify-end gap-2">
            <button type="button" @click="openModal('create-folder-modal')"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-gdrive-yellow hover:bg-gdrive-yellow/90 transition ease-in-out duration-150">
                <i class="fas fa-folder-plus mr-2"></i>
                Criar Pasta
            </button>
            <button type="button" onclick="document.getElementById('file-input').click()"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm leading-5 font-medium rounded-md text-white bg-gdrive-blue hover:bg-gdrive-blue/90 transition ease-in-out duration-150">
                <i class="fas fa-file-upload mr-2"></i>
                Adicionar Arquivo
            </button>
            <!-- Input de arquivo invisível que será acionado pelo botão acima -->
            <form id="direct-upload-form" action="{{ route('arquivos.upload') }}" method="POST"
                enctype="multipart/form-data" class="hidden">
                @csrf
                <input type="hidden" name="current_folder" value="{{ $currentFolder }}">
                <input type="file" name="file" id="file-input" class="hidden" onchange="this.form.submit()">
            </form>
        </div>

        <!-- Files Section -->
        <div class="bg-dark-surface w-full rounded-lg overflow-hidden shadow-lg">
            <!-- Path Navigation -->
            <div class="px-6 py-4 border-b border-dark-border bg-dark-surface">
                <div class="flex items-center space-x-2">
                    <a href="{{ route('arquivos.index') }}" class="text-gdrive-blue hover:underline">
                        <i class="fas fa-home mr-1"></i> Início
                    </a>
                    @if (!empty($currentFolder))
                        <span class="text-gray-400">/</span>
                        @php
                            // Usar uma variável de exibição para não alterar a variável original
                            $displayFolder = $currentFolder;
                            if ($currentFolder === '/') {
                                $displayFolder = 'Diretório Raiz';
                            }
                            $pathParts = explode('/', $displayFolder);
                            $currentPath = '';
                        @endphp
                        @foreach ($pathParts as $index => $part)
                            @php
                                $currentPath .= ($index > 0 ? '/' : '') . $part;
                                $isLast = $index == count($pathParts) - 1;
                            @endphp
                            @if ($isLast)
                                <span class="text-gray-300">{{ $part }}</span>
                            @else
                                <a href="{{ route('arquivos.index', ['folder' => $currentPath]) }}"
                                    class="text-gdrive-blue hover:underline">
                                    {{ $part }}
                                </a>
                                <span class="text-gray-400">/</span>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Table Header -->
            <div
                class="grid grid-cols-12 gap-4 px-6 py-4 border-b border-dark-border bg-darksurface text-white font-medium">
                <div class="col-span-8">Nome</div>
                <div class="col-span-2 text-right">Tamanho</div>
                <div class="col-span-2 text-right">Modificado em</div>
            </div>

            <!-- Folders List -->
            <div id="folders-container">
                @if (count($folders) > 0)
                    @foreach ($folders as $folder)
                        <div class="folder-item mb-1 overflow-hidden border-b border-dark-border"
                            x-show="isItemVisible('{{ $folder['basename'] }}')" x-transition>
                            <div class="grid grid-cols-12 gap-4 px-6 py-4 hover:bg-dark-hover transition duration-150 ease-in-out cursor-pointer"
                                @click="window.location.href='{{ route('arquivos.index', ['folder' => $folder['name']]) }}'"
                                @contextmenu.prevent="showContextMenu($event, {{ json_encode($folder) }}, null)">
                                <div class="col-span-8 flex items-center space-x-3">
                                    <i class="fas fa-folder text-gdrive-yellow text-xl w-7 text-center"></i>
                                    <span class="truncate folder-name">{{ $folder['basename'] }}</span>
                                </div>
                                <div class="col-span-2 text-gray-400 flex items-center justify-end">
                                    -
                                </div>
                                <div class="col-span-2 text-gray-400 flex items-center justify-end">
                                    {{ \Carbon\Carbon::createFromTimestamp($folder['last_modified'])->format('d/m/Y H:i:s') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- Files List -->
            <div id="files-container">
                @if (count($files) > 0)
                    @foreach ($files as $file)
                        @php
                            // Formatar o tamanho do arquivo
                            $size = $file['size'];
                            if ($size < 1024) {
                                $formattedSize = $size . ' B';
                            } elseif ($size < 1024 * 1024) {
                                $formattedSize = round($size / 1024, 2) . ' KB';
                            } elseif ($size < 1024 * 1024 * 1024) {
                                $formattedSize = round($size / (1024 * 1024), 2) . ' MB';
                            } else {
                                $formattedSize = round($size / (1024 * 1024 * 1024), 2) . ' GB';
                            }

                            // Determinar o ícone com base na extensão
                            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $iconClass = 'far fa-file text-gray-400';
                            $iconColor = 'text-gray-400';

                            switch (strtolower($extension)) {
                                case 'pdf':
                                    $iconClass = 'far fa-file-pdf';
                                    $iconColor = 'text-gdrive-red';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $iconClass = 'far fa-file-word';
                                    $iconColor = 'text-gdrive-blue';
                                    break;
                                case 'xls':
                                case 'xlsx':
                                    $iconClass = 'far fa-file-excel';
                                    $iconColor = 'text-gdrive-green';
                                    break;
                                case 'ppt':
                                case 'pptx':
                                    $iconClass = 'far fa-file-powerpoint';
                                    $iconColor = 'text-gdrive-yellow';
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif':
                                case 'webp':
                                    $iconClass = 'far fa-file-image';
                                    $iconColor = 'text-purple-400';
                                    break;
                                case 'mp4':
                                case 'mov':
                                case 'avi':
                                case 'webm':
                                    $iconClass = 'far fa-file-video';
                                    $iconColor = 'text-pink-400';
                                    break;
                                case 'mp3':
                                case 'wav':
                                case 'ogg':
                                    $iconClass = 'far fa-file-audio';
                                    $iconColor = 'text-orange-400';
                                    break;
                                case 'zip':
                                case 'rar':
                                case '7z':
                                    $iconClass = 'far fa-file-archive';
                                    $iconColor = 'text-amber-400';
                                    break;
                                case 'html':
                                case 'css':
                                case 'js':
                                case 'php':
                                case 'py':
                                case 'java':
                                    $iconClass = 'far fa-file-code';
                                    $iconColor = 'text-cyan-400';
                                    break;
                                case 'txt':
                                    $iconClass = 'far fa-file-alt';
                                    $iconColor = 'text-gray-400';
                                    break;
                            }
                        @endphp <div class="file-item mb-1 overflow-hidden border-b border-dark-border"
                            x-show="isItemVisible('{{ $file['basename'] }}')" x-transition>
                            <!-- File Info (Always visible) -->
                            <div class="grid grid-cols-12 gap-4 px-6 py-4 hover:bg-dark-hover transition duration-150 ease-in-out cursor-pointer"
                                @click="window.open('https://drive.google.com/file/d/{{ $file['id'] }}/view', '_blank')"
                                @contextmenu.prevent="showContextMenu($event, {{ json_encode($file) }}, {{ $loop->index }})">
                                <div class="col-span-8 flex items-center space-x-3">
                                    <i class="{{ $iconClass }} {{ $iconColor }} text-xl w-7 text-center"></i>
                                    <span class="truncate file-name">{{ $file['basename'] }}</span>
                                </div>
                                <div class="col-span-2 text-gray-400 flex items-center justify-end">
                                    {{ $formattedSize }}
                                </div>
                                <div class="col-span-2 text-gray-400 flex items-center justify-end">
                                    {{ \Carbon\Carbon::createFromTimestamp($file['last_modified'])->format('d/m/Y H:i:s') }}
                                </div>
                            </div>

                        </div>
                    @endforeach
                @endif

                @if (empty($folders) && empty($files))
                    <div class="text-center py-16">
                        <i class="far fa-folder-open text-5xl text-gray-400 mb-4"></i>
                        <p class="text-gray-400">Nenhum arquivo ou pasta encontrado no Google Drive</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Menu de contexto suspenso -->
    <div x-show="contextMenu.visible" x-cloak
        :style="`position: fixed; left: ${contextMenu.x}px; top: ${contextMenu.y}px; z-index: 60;`"
        class="context-menu bg-dark-bg shadow-xl rounded-md border border-dark-border w-60"
        @click.outside="hideContextMenu">
        <div class="py-1">
            <template x-if="contextMenu.fileData">
                <div>
                    <!-- Menu para arquivos -->
                    <template x-if="!contextMenu.isFolder">
                        <div>
                            <a :href="`https://drive.google.com/file/d/${contextMenu.fileData.id}/view`"
                                target="_blank"
                                class="context-menu-item flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="far fa-eye mr-3 w-5 text-center text-gray-400"></i>
                                <span>Visualizar</span>
                            </a>

                            <a :href="`/arquivos/download/${contextMenu.fileData.name}`"
                                class="context-menu-item flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="fas fa-download mr-3 w-5 text-center text-gray-400"></i>
                                <span>Baixar</span>
                            </a>

                            <hr class="border-dark-border my-1">

                            <button type="button" @click="showRenameForm(contextMenu.loopIndex)"
                                class="context-menu-item w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="fas fa-edit mr-3 w-5 text-center text-gray-400"></i>
                                <span>Renomear</span>
                            </button>

                            <button type="button" @click="showMoveForm(contextMenu.loopIndex)"
                                class="context-menu-item w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="fas fa-folder-open mr-3 w-5 text-center text-gray-400"></i>
                                <span>Mover para</span>
                            </button>

                            <hr class="border-dark-border my-1">

                            <form :action="`/arquivos/excluir`" method="POST"
                                onsubmit="return confirm('Tem certeza que deseja excluir este arquivo?');">
                                @csrf
                                <input type="hidden" name="file_path" :value="contextMenu.fileData.name">
                                <button type="submit"
                                    class="context-menu-item w-full text-left flex items-center px-4 py-2.5 text-sm text-gdrive-red">
                                    <i class="fas fa-trash-alt mr-3 w-5 text-center"></i>
                                    <span>Mover para a lixeira</span>
                                </button>
                            </form>
                        </div>
                    </template>

                    <!-- Menu para pastas -->
                    <template x-if="contextMenu.isFolder">
                        <div>
                            <a :href="`/arquivos/download-pasta/${contextMenu.fileData.name}`"
                                class="context-menu-item flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="fas fa-download mr-3 w-5 text-center text-gray-400"></i>
                                <span>Baixar como ZIP</span>
                            </a>

                            <hr class="border-dark-border my-1">

                            <button type="button" @click="showRenameFolderForm()"
                                class="context-menu-item w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="fas fa-edit mr-3 w-5 text-center text-gray-400"></i>
                                <span>Renomear</span>
                            </button>

                            <button type="button" @click="showMoveFolderForm()"
                                class="context-menu-item w-full text-left flex items-center px-4 py-2.5 text-sm text-gray-200">
                                <i class="fas fa-folder-open mr-3 w-5 text-center text-gray-400"></i>
                                <span>Mover para</span>
                            </button>

                            <hr class="border-dark-border my-1">

                            <form action="{{ route('arquivos.excluir-pasta') }}" method="POST"
                                onsubmit="return confirm('Tem certeza que deseja excluir esta pasta e todo seu conteúdo?');">
                                @csrf
                                <input type="hidden" name="folder_path" :value="contextMenu.fileData.name">
                                <button type="submit"
                                    class="context-menu-item w-full text-left flex items-center px-4 py-2.5 text-sm text-gdrive-red">
                                    <i class="fas fa-trash-alt mr-3 w-5 text-center"></i>
                                    <span>Excluir pasta</span>
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Formulário de Renomear Pasta (Hidden by default) -->
    <div id="rename-folder-form" class="fixed inset-0 hidden z-50">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-dark-surface p-6 rounded-lg shadow-lg w-full max-w-md relative z-10">
                <h3 class="text-lg font-medium mb-4">Renomear Pasta</h3>
                <form action="{{ route('arquivos.renomear-pasta') }}" method="POST"
                    class="flex flex-col space-y-4">
                    @csrf
                    <input type="hidden" name="old_path" id="folder_old_path">
                    <div class="flex-grow">
                        <label for="folder_new_name" class="block text-sm font-medium text-gray-400 mb-1">Novo nome da
                            pasta</label>
                        <input type="text" name="new_name" id="folder_new_name" required
                            class="w-full bg-dark-bg border border-dark-border rounded py-2 px-3 focus:outline-none focus:ring-2 focus:ring-gdrive-blue text-gray-200">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gdrive-yellow hover:bg-gdrive-yellow/90 transition ease-in-out duration-150">
                            <i class="fas fa-check mr-2"></i>
                            Renomear
                        </button>
                        <button type="button" @click="closeModal('rename-folder-form')"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-dark-surface hover:bg-dark-hover transition ease-in-out duration-150">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulário de Renomear Arquivo (Hidden by default) -->
    <div id="rename-file-modal" class="fixed inset-0 hidden z-50">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-dark-surface p-6 rounded-lg shadow-lg w-full max-w-md relative z-10">
                <h3 class="text-lg font-medium mb-4">Renomear Arquivo</h3>
                <form action="{{ url('/arquivos/renomear') }}" method="POST" class="flex flex-col space-y-4">
                    @csrf
                    <input type="hidden" name="old_path" id="file_old_path">
                    <div class="flex-grow">
                        <label for="file_new_name" class="block text-sm font-medium text-gray-400 mb-1">Novo nome do
                            arquivo</label>
                        <div class="flex items-center">
                            <input type="text" name="new_name" id="file_new_name" required
                                class="w-full bg-dark-bg border border-dark-border rounded-l py-2 px-3 focus:outline-none focus:ring-2 focus:ring-gdrive-blue text-gray-200">
                            <span id="file_extension_display"
                                class="bg-dark-surface border-t border-r border-b border-dark-border rounded-r py-2 px-3 text-gray-400"></span>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gdrive-yellow hover:bg-gdrive-yellow/90 transition ease-in-out duration-150">
                            <i class="fas fa-check mr-2"></i>
                            Renomear
                        </button>
                        <button type="button" @click="closeModal('rename-file-modal')"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-dark-surface hover:bg-dark-hover transition ease-in-out duration-150">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulário de Mover Arquivo (Hidden by default) -->
    <div id="move-file-modal" class="fixed inset-0 hidden z-50">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-dark-surface p-6 rounded-lg shadow-lg w-full max-w-md relative z-10">
                <h3 class="text-lg font-medium mb-4">Mover Arquivo</h3>
                <form action="{{ url('/arquivos/mover') }}" method="POST" class="flex flex-col space-y-4">
                    @csrf
                    <input type="hidden" name="file_path" id="move_file_path">
                    <div class="flex-grow">
                        <label for="move_destination"
                            class="block text-sm font-medium text-gray-400 mb-1">Destino</label>
                        <select name="new_folder" id="move_destination" required
                            class="w-full bg-dark-bg border border-dark-border rounded py-2 px-3 focus:outline-none focus:ring-2 focus:ring-gdrive-blue text-gray-200">
                            @foreach ($allFolders as $folder)
                                <option value="{{ $folder['path'] }}">{{ $folder['name'] ?: 'Raiz' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gdrive-blue hover:bg-gdrive-blue/90 transition ease-in-out duration-150">
                            <i class="fas fa-check mr-2"></i>
                            Mover
                        </button>
                        <button type="button" @click="closeModal('move-file-modal')"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-dark-surface hover:bg-dark-hover transition ease-in-out duration-150">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulário de Mover Pasta (Hidden by default) -->
    <div id="move-folder-modal" class="fixed inset-0 hidden z-50">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-dark-surface p-6 rounded-lg shadow-lg w-full max-w-md relative z-10">
                <h3 class="text-lg font-medium mb-4">Mover Pasta</h3>
                <form action="{{ route('arquivos.mover-pasta') }}" method="POST" class="flex flex-col space-y-4">
                    @csrf
                    <input type="hidden" name="folder_path" id="move_folder_path">
                    <div class="flex-grow">
                        <label for="move_folder_destination"
                            class="block text-sm font-medium text-gray-400 mb-1">Destino</label>
                        <select name="new_folder" id="move_folder_destination" required
                            class="w-full bg-dark-bg border border-dark-border rounded py-2 px-3 focus:outline-none focus:ring-2 focus:ring-gdrive-blue text-gray-200">
                            @foreach ($allFolders as $folder)
                                <option value="{{ $folder['path'] }}">{{ $folder['name'] ?: 'Raiz' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gdrive-blue hover:bg-gdrive-blue/90 transition ease-in-out duration-150">
                            <i class="fas fa-check mr-2"></i>
                            Mover
                        </button>
                        <button type="button" @click="closeModal('move-folder-modal')"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-dark-surface hover:bg-dark-hover transition ease-in-out duration-150">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function driveApp() {
            return {
                searchTerm: '',
                contextMenu: {
                    visible: false,
                    x: 0,
                    y: 0,
                    fileData: null,
                    loopIndex: null,
                    isFolder: false
                },

                init() {
                    // Fix for any floating buttons - ensure all modals are properly hidden on page load
                    const allModals = ['rename-folder-form', 'rename-file-modal', 'move-file-modal', 'move-folder-modal',
                        'create-folder-modal'
                    ];
                    allModals.forEach(id => {
                        document.getElementById(id).classList.add('hidden');
                    });
                },

                // Funções de gerenciamento de modais
                openModal(modalId) {
                    // Garante que todos os outros modais estejam fechados primeiro
                    const allModals = ['rename-folder-form', 'rename-file-modal', 'move-file-modal', 'move-folder-modal',
                        'create-folder-modal'
                    ];
                    allModals.forEach(id => {
                        if (id !== modalId) {
                            document.getElementById(id).classList.add('hidden');
                        }
                    });
                    // Abre o modal solicitado
                    document.getElementById(modalId).classList.remove('hidden');
                },

                closeModal(modalId) {
                    document.getElementById(modalId).classList.add('hidden');
                },

                // Mostra o menu de contexto
                showContextMenu(event, fileData, loopIndex) {
                    event.preventDefault();

                    // Define os dados do arquivo e o índice do loop
                    this.contextMenu.fileData = fileData;
                    this.contextMenu.loopIndex = loopIndex;
                    // Determinar se é uma pasta com base no loopIndex (null para pastas)
                    this.contextMenu.isFolder = loopIndex === null;

                    // Calcula a posição ideal para o menu
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    const menuWidth = 240; // Largura aproximada do menu (w-60 = 15rem = ~240px)
                    const menuHeight = 280; // Altura aproximada estimada

                    // Posição inicial baseada no clique
                    let x = event.clientX;
                    let y = event.clientY;

                    // Ajusta se estiver muito perto da borda direita
                    if (x + menuWidth > viewportWidth) {
                        x = viewportWidth - menuWidth - 10;
                    }

                    // Ajusta se estiver muito perto da borda inferior
                    if (y + menuHeight > viewportHeight) {
                        y = viewportHeight - menuHeight - 10;
                    }

                    // Define a posição e torna o menu visível
                    this.contextMenu.x = x;
                    this.contextMenu.y = y;
                    this.contextMenu.visible = true;

                    // Fecha o menu de contexto quando clicar fora dele
                    document.addEventListener('click', this.hideContextMenu = () => {
                        this.contextMenu.visible = false;
                        document.removeEventListener('click', this.hideContextMenu);
                    });
                },

                // Esconde o menu de contexto
                hideContextMenu() {
                    this.contextMenu.visible = false;
                    // Remover o event listener para evitar memory leaks
                    document.removeEventListener('click', this.hideContextMenu);
                },

                // Abre o formulário de renomear para arquivo
                showRenameForm(index) {
                    this.hideContextMenu();
                    const fileData = this.contextMenu.fileData;

                    // Extrair o nome do arquivo e extensão
                    const fileName = fileData.basename;
                    const lastDotIndex = fileName.lastIndexOf('.');

                    // Se não houver ponto ou se for o primeiro caractere, não há extensão
                    if (lastDotIndex > 0) {
                        const fileNameWithoutExt = fileName.substring(0, lastDotIndex);
                        const extension = fileName.substring(lastDotIndex);
                        document.getElementById('file_new_name').value = fileNameWithoutExt;
                        document.getElementById('file_extension_display').textContent = extension;
                    } else {
                        // Se não há extensão, apenas usar o nome do arquivo
                        document.getElementById('file_new_name').value = fileName;
                        document.getElementById('file_extension_display').textContent = '';
                    }

                    document.getElementById('file_old_path').value = fileData.name;
                    this.openModal('rename-file-modal');
                },

                // Abre o formulário de renomear para pasta
                showRenameFolderForm() {
                    this.hideContextMenu();
                    // Mostra o form de renomear pasta que está associado ao contextMenu.fileData.name
                    this.openModal('rename-folder-form');
                    document.getElementById('folder_old_path').value = this.contextMenu.fileData.name;
                    document.getElementById('folder_new_name').value = this.contextMenu.fileData.basename;
                },

                // Abre o formulário de mover
                showMoveForm(index) {
                    this.hideContextMenu();
                    const fileData = this.contextMenu.fileData;
                    const filePath = fileData.name;
                    document.getElementById('move_file_path').value = filePath;

                    // Atualiza as opções no select, restaurando todas as opções
                    const select = document.getElementById('move_destination');
                    const options = select.options;

                    // Habilita todas as opções
                    for (let i = 0; i < options.length; i++) {
                        options[i].disabled = false;
                    }

                    this.openModal('move-file-modal');
                },

                // Abre o formulário de mover pasta
                showMoveFolderForm() {
                    this.hideContextMenu();
                    const folderData = this.contextMenu.fileData;
                    const folderPath = folderData.name;
                    document.getElementById('move_folder_path').value = folderPath;

                    // Atualiza as opções no select, removendo a pasta atual e subpastas
                    const select = document.getElementById('move_folder_destination');
                    const options = select.options;

                    // Limpa seleção anterior
                    for (let i = 0; i < options.length; i++) {
                        options[i].disabled = false;
                    }

                    // Desabilita a própria pasta e subpastas
                    for (let i = 0; i < options.length; i++) {
                        const optionPath = options[i].value;
                        if (optionPath === folderPath || optionPath.startsWith(folderPath + '/')) {
                            options[i].disabled = true;
                        }
                    }

                    // Seleciona o primeiro item não desabilitado
                    for (let i = 0; i < options.length; i++) {
                        if (!options[i].disabled) {
                            options[i].selected = true;
                            break;
                        }
                    }

                    this.openModal('move-folder-modal');
                },

                // Verifica se o item (arquivo ou pasta) deve ser exibido com base no termo de pesquisa
                isItemVisible(name) {
                    if (!this.searchTerm) return true;
                    return name.toLowerCase().includes(this.searchTerm.toLowerCase());
                }
            }
        }
    </script>

    <!-- Modal de Criar Pasta (Hidden by default) -->
    <div id="create-folder-modal" class="fixed inset-0 hidden z-50">
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-dark-surface p-6 rounded-lg shadow-lg w-full max-w-md relative z-10">
                <h3 class="text-lg font-medium mb-4">Criar Nova Pasta</h3>
                <form action="{{ route('arquivos.criar-pasta') }}" method="POST" class="flex flex-col space-y-4">
                    @csrf
                    <input type="hidden" name="current_folder" value="{{ $currentFolder }}">
                    <div class="flex-grow">
                        <label for="folder_name" class="block text-sm font-medium text-gray-400 mb-1">Nome da nova
                            pasta</label>
                        <input type="text" name="folder_name" id="folder_name" required
                            class="w-full bg-dark-bg border border-dark-border rounded py-2 px-3 focus:outline-none focus:ring-2 focus:ring-gdrive-blue text-gray-200"
                            placeholder="Ex: Minha Pasta">
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="submit"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-gdrive-yellow hover:bg-gdrive-yellow/90 transition ease-in-out duration-150">
                            <i class="fas fa-check mr-2"></i>
                            Criar
                        </button>
                        <button type="button" @click="closeModal('create-folder-modal')"
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-dark-surface hover:bg-dark-hover transition ease-in-out duration-150">
                            <i class="fas fa-times mr-2"></i>
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
