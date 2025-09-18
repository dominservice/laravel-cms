# Dominservice Laravel CMS
[![Latest Version on Packagist](https://img.shields.io/packagist/v/dominservice/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-cms)
[![Total Downloads](https://img.shields.io/packagist/dt/dominservice/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-cms)
[![License](https://img.shields.io/packagist/l/dominservice/laravel-cms.svg?style=flat-square)](https://packagist.org/packages/dominservice/laravel-cms)


Kompletny pakiet CMS dla aplikacji Laravel (9–12), dostarczający struktury danych dla treści i kategorii (wielojęzyczność, drzewo kategorii), metadanych plików (avatar/dodatkowe), prostego wideo oraz elastycznej konfiguracji rozmiarów plików. Dokument ten zawiera pełną instrukcję instalacji, konfiguracji i użycia wraz z przykładami.

Spis treści
- Wymagania
- Instalacja
- Publikowanie konfiguracji i migracji
- Uruchomienie migracji
- Konfiguracja (config/cms.php)
  - Tabele
  - Dyski
  - Avatar (dziedziczenie rozszerzenia)
  - Pliki i rozmiary (content/category)
- Modele i relacje
  - Content
  - Category
  - ContentFile i CategoryFile (metadane plików)
  - ContentVideo
- Generowanie nazw plików
- Dostęp do avataru i rozmiarów (trait DynamicAvatarAccessor)
- Przykłady użycia
  - Tworzenie kategorii (drzewo)
  - Tworzenie treści i powiązanie z kategoriami
  - Zapis plików (avatar i dodatkowe)
  - Pobieranie adresów URL dla rozmiarów
  - Wideo
- Upload plików (helper Media)
- Rozszerzanie: nowe typy plików i rozmiary
- Uwagi dot. zgodności wstecznej
- FAQ/Troubleshooting
- Licencja

Wymagania
- PHP >= 8.0
- Laravel 9.x, 10.x, 11.x lub 12.x
- astrotomic/laravel-translatable ^11.13 (wielojęzyczność)
- kalnoy/nestedset ^6.0 (drzewo kategorii)

Instalacja
1) Zainstaluj pakiet:
```bash
composer require dominservice/laravel-cms
```

2) Pakiet korzysta z autodetekcji ServiceProvidera, więc nie wymaga ręcznej rejestracji.

Publikowanie konfiguracji i migracji
- Konfiguracja:
```bash
php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=config
```

- Migracje (tabele CMS, powiązania, wideo, itp.):
```bash
php artisan vendor:publish --provider="Dominservice\\LaravelCms\\ServiceProvider" --tag=migrations
```

Uwaga: Po opublikowaniu migracji sprawdź folder database/migrations i w razie potrzeby zweryfikuj zgodność nazw plików migracji oraz ich kolejność. W zależności od wersji pakietu zestaw migracji może się różnić.

Uruchomienie migracji
```bash
php artisan migrate
```

Konfiguracja (config/cms.php)
Poniżej podsumowanie najważniejszych opcji. Zawsze sprawdź aktualny plik konfiguracyjny po opublikowaniu.
- date_format, time_format – formatowanie dat w accessorach modeli.
- url_route – (opcjonalnie) bazowa część ścieżek URL używana przez aplikację.
- tables – nazwy tabel, jakie pakiet wykorzystuje.
- disks – nazwy dysków Laravel Storage dla poszczególnych typów zasobów (content, category, content_video). Upewnij się, że odpowiadają one zdefiniowanym dyskom w config/filesystems.php (np. public).
- avatar.extension – rozszerzenie pliku obrazka wykorzystywane przy generowaniu nazw (domyślnie webp).
- files.content/types oraz files.category/types – definicja typów (np. avatar, additional) i ich rozmiarów. Każdy rozmiar to klucz (np. original, large, small, thumb) oraz ustawienia wymiarów. Klucz display określa, który rozmiar ma być eksponowany jako avatar_path.

Przykładowy fragment (skrócony):
```php
files => [
  'content' => [
    'types' => [
      'avatar' => [
        'display' => 'large',
        'sizes' => [
          'original' => null,
          'large' => ['w' => 1920, 'h' => 1080, 'fit' => 'contain'],
          'small' => ['w' => 640, 'h' => 360, 'fit' => 'contain'],
          'thumb' => ['w' => 160, 'h' => 160, 'fit' => 'cover'],
        ],
      ],
      'additional' => [
        'sizes' => [/* ... */],
      ],
    ],
  ],
  'category' => [ /* analogicznie */ ],
]
```

Modele i relacje
- Dominservice\LaravelCms\Models\Content
  - Wielojęzyczny (Astrotomic Translatable) – atrybuty translatedAttributes: slug, name, sub_name, short_description, description, meta_*
  - Relacje: categories() (MTM), rootCategory(), video(), files(), avatarFile()
  - Appendowane atrybuty: avatar_path, small_avatar_path, thumb_avatar_path, video_path

- Dominservice\LaravelCms\Models\Category
  - Wielojęzyczny, drzewo (Nestedset)
  - Relacje: contents() (MTM), files(), avatarFile()
  - Appendowany atrybut: avatar_path

- Dominservice\LaravelCms\Models\ContentFile i CategoryFile
  - Przechowują metadane plików w tabelach zależnych (cms_content_files, cms_category_files)
  - Kolumny: uuid, *_uuid, kind ('avatar', 'additional', 'video_avatar', 'video_poster' itp.), type (rodzaj pliku: 'image' lub 'video'), names (JSON: mapa rozmiar => nazwa pliku), timestamps, softDeletes

- Dominservice\LaravelCms\Models\ContentVideo
  - Prosta relacja z Content, pozwala przechowywać nazwę pliku wideo i udostępniać URL przez content->video_path

Generowanie nazw plików
Do generowania unikalnych nazw obrazów służy helper:
```php
Dominservice\LaravelCms\Helpers\Name::generateImageName(string $prefix = null): string
```
- Zwraca bazę nazwy w formacie: [opcjonalny-prefix]-ULID.webp (rozszerzenie pobierane z config('cms.avatar.extension')).
- Nazwa NIE wykorzystuje pól z modelu – jest w pełni niezależna i stabilna (wymaganie zgodne z ostatnimi zmianami).

Dostęp do avataru i rozmiarów (trait DynamicAvatarAccessor)
Modele Content i Category używają traitu: Dominservice\LaravelCms\Traits\DynamicAvatarAccessor
- avatar_path – zwraca URL rozmiaru określonego w konfiguracji w kluczu files.{content|category}.types.avatar.display (domyślnie large).
- Dynamiczny dostęp do innych rozmiarów: {size}_avatar_path, np.: small_avatar_path, large_avatar_path, thumb_avatar_path. Zwracają URL lub null, jeśli brak.
- Trait odczytuje nazwy plików z tabel zależnych (names[size]) i używa właściwego dysku z config('cms.disks.{content|category}').

Przykłady użycia
1) Tworzenie kategorii (drzewo)
```php
use Dominservice\LaravelCms\Models\Category;

$cat = new Category(['type' => 'section', 'status' => 1]);
$cat->save();
$cat->translateOrNew('pl')->name = 'Aktualności';
$cat->translateOrNew('pl')->slug = 'aktualnosci';
$cat->save();
```

2) Tworzenie treści i powiązanie z kategoriami
```php
use Dominservice\LaravelCms\Models\Content;

$content = new Content(['type' => 'article', 'status' => 1]);
$content->save();
$content->translateOrNew('pl')->name = 'Pierwszy wpis';
$content->translateOrNew('pl')->slug = 'pierwszy-wpis';
$content->save();

// Powiązanie z kategorią
$content->categories()->attach($cat->uuid);
```

3) Zapis plików (avatar i dodatkowe)
Założenia: korzystasz z dysku public i masz skonfigurowane linki storage:link.

```php
use Illuminate\Support\Facades\Storage;
use Dominservice\LaravelCms\Models\ContentFile;
use Dominservice\LaravelCms\Helpers\Name;

// Przykład: zapis avataru w różnych rozmiarach (nazwy musisz wytworzyć i przetworzyć obrazy po swojej stronie)
$names = [
  'original' => Name::generateImageName('content'),
  'large'    => Name::generateImageName('content-large'),
  'small'    => Name::generateImageName('content-small'),
  'thumb'    => Name::generateImageName('content-thumb'),
];

// Zapis fizycznych plików na dysku (przykładowo)
$disk = config('cms.disks.content');
Storage::disk($disk)->put($names['original'], $binaryOriginal);
Storage::disk($disk)->put($names['large'], $binaryLarge);
Storage::disk($disk)->put($names['small'], $binarySmall);
Storage::disk($disk)->put($names['thumb'], $binaryThumb);

// Zapis metadanych w tabeli zależnej
ContentFile::create([
  'content_uuid' => $content->uuid,
  'kind' => 'avatar',
  'type' => null,
  'names' => $names,
]);

// Dodatkowe pliki (kind = 'additional') zapisujesz analogicznie
```

4) Pobieranie adresów URL dla rozmiarów
```php
// Zgodnie z konfiguracją display, to będzie np. URL do large
$url = $content->avatar_path; 

// Dostęp do konkretnych rozmiarów
$small = $content->small_avatar_path; // lub null jeśli brak pliku
$thumb = $content->thumb_avatar_path;

// Dla kategorii działa analogicznie
$catUrl = $cat->avatar_path;
```

5) Wideo
Jeśli masz relację Content->video oraz plik wideo zapisany na dysku config('cms.disks.content_video'), a w tabeli wideo nazwa pliku jest przypisana – otrzymasz URL przez accessor:
```php
$videoUrl = $content->video_path; // null jeśli brak lub plik nie istnieje
```

Nowość: Avatar wideo w wielu rozmiarach + obraz pierwszej klatki (poster)
- Konfiguracja w config/cms.php:
  - files.content.types.video_avatar.sizes – lista dopuszczalnych wariantów wideo (np. hd, sd, mobile)
  - files.content.types.video_avatar.display – który wariant ma być zwracany przez $content->video_avatar_path
  - files.content.types.video_poster.sizes – rozmiary obrazka postera (przetwarzane jak obrazy)

Upload wielu plików wideo (bez transkodowania) jako „video_avatar”:
```php
use Dominservice\\LaravelCms\\Helpers\\Media;

Media::uploadModelVideos($content, [
    'hd'     => request()->file('video_hd'),     // UploadedFile lub ścieżka do pliku mp4/webm itp.
    'sd'     => request()->file('video_sd'),
    'mobile' => request()->file('video_mobile'),
], 'video_avatar');

// Po zapisie
$defaultVideo = $content->video_avatar_path; // URL do wariantu zdefiniowanego w display (domyślnie 'hd')
```

Upload obrazka pierwszej klatki (poster) – działa jak obrazy, generuje rozmiary zgodnie z config:
```php
Media::uploadModelImage($content, request()->file('video_poster'), 'video_poster');

$poster = $content->video_poster_path; // URL do rozmiaru wskazanego w display (domyślnie 'large')
```

Upload plików (helper Media)
Pakiet zawiera wbudowany helper do przetwarzania i zapisu obrazów wraz z generowaniem wielu rozmiarów oraz automatyczną synchronizacją nazw w tabelach zależnych.

Semantyka kolumny type w *_files
- Od tej wersji kolumna type w tabelach cms_content_files i cms_category_files określa bazowy rodzaj pliku: 'image' albo 'video'.
- Helper Media ustawia to automatycznie:
  - uploadModelImage(...): type domyślnie = 'image'
  - uploadModelVideos(...): type domyślnie = 'video'
- Dla postera wideo (kind = 'video_poster') helper traktuje go jak obraz (type = 'image').

Lista obrazów i wideo na modelu (trait)
Modele Content i Category używają traitu DynamicAvatarAccessor, który udostępnia pomocnicze metody:
- $model->imageFilesList() – kolekcja rekordów *_files, gdzie type = 'image'
- $model->videoFilesList() – kolekcja rekordów *_files, gdzie type = 'video'

Uwaga dot. postera
- Poster wideo jest przechowywany jako osobny rekord w *_files o kind = 'video_poster' i type = 'image'.
- Powiązanie „wideo ↔ poster” jest realizowane konwencją przez wspólnego właściciela (content_uuid/category_uuid) i rodzaje kind; na ten moment nie ma dodatkowego klucza relacyjnego między rekordami.*

Sygnatura metody:
```php
use Dominservice\LaravelCms\Helpers\Media;

Media::uploadModelImage(
    \Illuminate\Database\Eloquent\Model $model,          // Content lub Category
    \Illuminate\Http\UploadedFile|string $source,         // UploadedFile z requestu lub ścieżka do pliku na dysku
    string $kind = 'avatar',                                // 'avatar' lub 'additional' (lub inny zdefiniowany w configu)
    ?string $type = null,                                   // opcjonalny pod-typ, np. 'gallery'
    ?array $onlySizes = null,                               // np. ['large','thumb'] – wygeneruje wybrane rozmiary
    bool $replaceExisting = true                            // czy zastępować istniejący plik tego typu dla modelu
): \Illuminate\Database\Eloquent\Model;                   // Zwraca ContentFile lub CategoryFile
```

Opis parametrów i działania:
- model – instancja Content lub Category. Na podstawie modelu wybierany jest odpowiedni dysk (config('cms.disks.content'| 'category')) i gałąź konfiguracji rozmiarów.
- source – może być UploadedFile (np. request()->file('avatar')) albo pełna ścieżka do istniejącego pliku obrazu.
- kind – typ pliku zgodny z konfiguracją w config('cms.files.{content|category}.types'). Domyślnie 'avatar'.
- type – opcjonalny pod-typ, pozwala rozróżniać warianty w obrębie tego samego kind (np. 'gallery').
- onlySizes – jeśli podasz listę kluczy rozmiarów, helper wygeneruje tylko te warianty; gdy null, wygeneruje wszystkie zdefiniowane w configu dla danego kind.
- replaceExisting – jeśli true i istnieje już rekord dla (model, kind, type), helper usunie stare pliki z dysku i zaktualizuje rekord nazwami nowych plików.

Użyta konfiguracja rozmiarów:
- Definicje rozmiarów znajdują się w config('cms.files.content.types') i config('cms.files.category.types').
- Każdy rozmiar ma klucz (np. original, large, small, thumb). Wartość null oznacza zachowanie oryginalnych wymiarów (z reenkodowaniem do rozszerzenia z config('cms.avatar.extension')).
- Dla rozmiarów z parametrami możesz określić: w (szerokość), h (wysokość), fit ('contain' albo 'cover').

Przykłady
1) Upload avataru dla treści (z pliku z formularza)
```php
use Dominservice\LaravelCms\Helpers\Media;
use Dominservice\LaravelCms\Models\Content;

$content = Content::first();
$file = request()->file('avatar');

// Wygeneruje wszystkie rozmiary zdefiniowane dla 'avatar' i zapisze do cms_content_files
$record = Media::uploadModelImage($content, $file, 'avatar');

// Po zapisie możesz uzyskać URL zgodnie z konfiguracją display
$url = $content->avatar_path;            // np. 'large'
$thumb = $content->thumb_avatar_path;    // dostęp dynamiczny
```

2) Upload tylko wybranych rozmiarów (np. large i thumb)
```php
$record = Media::uploadModelImage($content, $file, 'avatar', null, ['large','thumb']);
```

3) Upload pliku dla kategorii z pod-typem (gallery), źródło jako ścieżka z dysku
```php
use Dominservice\LaravelCms\Models\Category;

$category = Category::first();
$path = storage_path('app/tmp/example.jpg');

$record = Media::uploadModelImage($category, $path, 'additional', 'gallery');
```

4) Zachowanie istniejących plików (bez usuwania i podmiany)
```php
// replaceExisting = false – helper doda/ustawi rekord tylko jeśli nie istnieje; istniejące pliki pozostaną nienaruszone
$record = Media::uploadModelImage($content, $file, 'avatar', null, null, false);
```

Dostęp do URL po uploadzie
- Dla avataru: $model->avatar_path zwróci URL rozmiaru wskazanego w files.{entity}.types.avatar.display.
- Inne rozmiary avataru dostępne dynamicznie: $model->small_avatar_path, $model->large_avatar_path, $model->thumb_avatar_path.
- Dla innych kind niż 'avatar' możesz pobierać nazwy z rekordu w *_files (pole names) i budować URL przez Storage::disk(config('cms.disks.{entity}'))->url($name).

Upload responsywny (jedno wywołanie: mobile + desktop)
W odpowiedzi na wymaganie: „w jednym odniesieniu dało się zaimplementować dwa pliki (mobile i desktop) oraz rezygnacja z oryginalnego pliku przy uploadzie” dodano nową metodę i zachowanie helpera Media:

- Oryginalny plik (klucz 'original' o wartości null w konfiguracji) nie jest już zapisywany przez helper – wpisy 'original' są ignorowane podczas generowania plików. Dzięki temu nie trzymamy zbędnej kopii.
- Nowa metoda do jednoczesnego uploadu dwóch źródeł (mobile i desktop):

```php
use Dominservice\LaravelCms\Helpers\Media;

Media::uploadModelResponsiveImages(
    $model,                                   // Content lub Category
    [
        'mobile'  => request()->file('img_mobile'),   // UploadedFile lub ścieżka
        'desktop' => request()->file('img_desktop'),  // UploadedFile lub ścieżka
    ],
    'avatar',          // kind
    null,              // type (opcjonalnie)
    ['large','thumb'], // onlySizes (opcjonalnie) – np. tylko wybrane rozmiary
    true               // replaceExisting
);
```

- Zapis w bazie (kolumna names) ma postać zagnieżdżonej struktury:

```json
{
  "mobile": {
    "large": "content-avatar-mobile-large-XXXX.webp",
    "thumb": "content-avatar-mobile-thumb-YYYY.webp"
  },
  "desktop": {
    "large": "content-avatar-desktop-large-ZZZZ.webp",
    "thumb": "content-avatar-desktop-thumb-WWWW.webp"
  }
}
```

- Dostęp do URL w accessorach:
  - avatar_path – domyślnie zwraca profil desktop dla rozmiaru display z konfiguracji.
  - {size}_avatar_path – dalej działa (np. large_avatar_path) i korzysta z profilu desktop, jeżeli zapisano struktury z profilami.
  - {profile}_{size}_avatar_path – jawnie dla profilu, np.:
    - mobile_large_avatar_path
    - desktop_thumb_avatar_path

Uwaga: Konfiguracja rozmiarów w config/cms.php może nadal zawierać klucz 'original', ale helper go zignoruje. Zalecane jest pozostawienie tylko potrzebnych rozmiarów z parametrami.

Upload z jednym plikiem domyślnym i nadpisaniami dla wybranych rozmiarów (default + overrides)
Czasami potrzebujesz przypisać inne źródło tylko do części rozmiarów (np. osobny obraz dla thumb), a dla reszty użyć jednego, wspólnego pliku. Służy do tego metoda:

```php
use Dominservice\LaravelCms\Helpers\Media;

Media::uploadModelImageWithDefaults(
    $model, // Content lub Category
    [
        'default' => request()->file('img_default'), // bazowy dla wszystkich rozmiarów
        'thumb'   => request()->file('img_thumb'),   // opcjonalne nadpisanie tylko dla 'thumb'
        // 'small' => request()->file('img_small'),  // inne opcjonalne nadpisania
    ],
    'avatar',          // kind
    null,              // type (opcjonalnie)
    null,              // onlySizes (opcjonalnie)
    true               // replaceExisting
);
```

Zasady działania:
- Dla każdego rozmiaru zdefiniowanego w configu (poza 'original') helper wybiera: źródło z klucza o nazwie rozmiaru (jeśli podane), w przeciwnym wypadku źródło z klucza 'default'.
- Jeśli dla danego rozmiaru nie ma ani nadpisania, ani 'default' – rozmiar jest pomijany.
- Co najmniej jeden wariant musi zostać wygenerowany, w przeciwnym razie zostanie rzucony InvalidArgumentException.
- Wpisy 'original' (null) w konfiguracji są ignorowane – nie zapisujemy oryginału.

Przykład minimalny (domyślny dla wszystkich poza thumb):
```php
Media::uploadModelImageWithDefaults($content, [
  'default' => request()->file('img_all'),
  'thumb'   => request()->file('img_only_thumb'),
], 'avatar');
```

Po zapisie:
- $model->avatar_path zwróci URL rozmiaru zdefiniowanego w display (np. 'large').
- Dostęp do konkretnych rozmiarów: $model->{size}_avatar_path (np. $model->thumb_avatar_path, $model->small_avatar_path).

Walidacja i obsługa błędów
- W razie błędnej konfiguracji lub nieudanego przetwarzania rzucony zostanie InvalidArgumentException. Możesz zabezpieczyć wywołanie:
```php
try {
    Media::uploadModelImage($content, $file, 'avatar');
} catch (\InvalidArgumentException $e) {
    // obsłuż błąd (np. komunikat dla użytkownika)
}
```

Wymagania środowiskowe
- Upewnij się, że skonfigurowane są właściwe dyski w config('cms.disks.*') oraz istnieje storage:link dla publicznego serwowania plików:
```bash
php artisan storage:link
```

- Rozszerzenie obrazów ustawiane jest przez config('cms.avatar.extension'), domyślnie webp.

Czyszczenie i podmiana
- Gdy replaceExisting = true, helper automatycznie usuwa poprzednie pliki z dysku przypisane do danego rekordu (model, kind, type) i zapisuje nowe nazwy w kolumnie names.

Rozszerzanie: nowe typy plików i rozmiary
- W pliku config/cms.php dodaj własny typ w sekcji files.{entity}.types, np. gallery, document.
- Zdefiniuj rozmiary (klucze i parametry). Następnie w logice zapisu generuj nazwy i uzupełniaj je w kolumnie names (JSON) w odpowiadających rekordach *_files.
- Dzięki temu możesz tworzyć dodatkowe accessorowe ścieżki dynamiczne np. for each size w avatarze; dla innych typów możesz dodać własne accessory lub korzystać bezpośrednio z pól w bazie.

Uwagi dot. zgodności wstecznej
- Nazwy plików nie są już generowane na podstawie atrybutów modelu – są niezależne (ULID + rozszerzenie z konfiguracji).
- Odczyt odbywa się wyłącznie na podstawie wartości zapisanych w tabelach zależnych (names[size]). Jeśli rekordów brak, accessory zwrócą null.
- Wybór rozmiaru avataru eksponowanego jako avatar_path jest kontrolowany przez konfigurację (display).

FAQ / Troubleshooting
- Nie widzę URL w avatar_path: Upewnij się, że istnieje rekord w *_files o kind = 'avatar' z wypełnionym names[display]. Sprawdź też, czy plik fizycznie istnieje na skonfigurowanym dysku i czy storage:link jest utworzone.
- Błąd ścieżek/dysków: Zweryfikuj config('cms.disks.*') oraz config/filesystems.php. Dysk musi być dostępny i poprawnie skonfigurowany.
- Wielojęzyczne pola nie zapisują się: Pamiętaj o translateOrNew('locale') i unikalności par (uuid, locale) w tabelach tłumaczeń.
- Struktura kategorii: Modele używają kalnoy/nestedset – posługuj się metodami z pakietu (np. appendToNode, ancestorsAndSelf, itp.).

Licencja
MIT


## Plan migracji danych i eliminacji ContentVideo

Cel: całkowicie przenieść przechowywanie informacji o wideo z tabeli cms_content_videos (model ContentVideo) do unified storage w cms_content_files (model ContentFile) z rozdzieleniem na:
- kind = "video_avatar" (type = "video") — wiele wariantów wideo (np. hd/sd/mobile)
- kind = "video_poster" (type = "image") — obraz pierwszej klatki (poster)

Po migracji ContentVideo będzie zbędny i może zostać usunięty.

Etapy (proponowana oś czasu)
1) Przygotowanie (Dzień 0)
- Upewnij się, że wdrożona jest wersja pakietu zawierająca:
  - modele ContentFile/CategoryFile z kolumną type = 'image'|'video'
  - helper Media::uploadModelVideos oraz Media::uploadModelImage dla kind = video_poster
  - accessor $content->video_avatar_path i $content->video_poster_path
- Zweryfikuj konfigurację:
  - config('cms.disks.content_video') wskazuje poprawny dysk (np. public)
  - config('cms.files.content.types.video_avatar.sizes') zawiera dopuszczalne klucze (np. hd/sd/mobile)
  - config('cms.files.content.types.video_avatar.display') ustawiony (np. hd)
  - config('cms.files.content.types.video_poster.sizes') i display ustawione (np. large)
- Wykonaj pełną kopię bazy i plików (storage/app/public). To krok obowiązkowy.

2) Backfill danych (Dzień 0)
Przenieś wpisy z cms_content_videos do cms_content_files w formie jednowariantowej (np. tylko 'hd'), bez utraty kompatybilności.

Wariant A: SQL (szybki backfill jednowariantowy)
- Założenie: w cms_content_videos jest plik źródłowy (np. 1 sztuka na content), który traktujemy jako wariant 'hd'.

Przykładowy SQL (MySQL/MariaDB):
```sql
INSERT INTO cms_content_files (uuid, content_uuid, kind, type, names, created_at, updated_at, deleted_at)
SELECT UUID(), v.content_uuid, 'video_avatar' AS kind, 'video' AS type,
       JSON_OBJECT('hd', v.name) AS names,
       NOW(), NOW(), NULL
FROM cms_content_videos v
LEFT JOIN cms_content_files f
  ON f.content_uuid = v.content_uuid AND f.kind = 'video_avatar' AND f.deleted_at IS NULL
WHERE f.uuid IS NULL;
```
Uwaga: UUID() można zastąpić generatorem zgodnym z Twoją bazą; jeśli kolumna uuid to CHAR(36) z aplikacyjnym UUID/ULID, rozważ backfill przez skrypt aplikacyjny (Wariant B), aby użyć helpera generującego.

Wariant B: Skrypt w Laravel (Eloquent) — bezpieczniejszy i elastyczny
```php
use Dominservice\LaravelCms\Models\Content;
use Dominservice\LaravelCms\Models\ContentFile;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    Content::query()
        ->with('video')
        ->whereHas('video')
        ->chunkById(200, function ($contents) {
            foreach ($contents as $content) {
                $exists = $content->files()
                    ->where('kind', 'video_avatar')
                    ->exists();
                if ($exists) { continue; }

                $name = optional($content->video)->name; // nazwa pliku wideo z legacy tabeli
                if (!$name) { continue; }

                ContentFile::create([
                    'content_uuid' => $content->uuid,
                    'kind' => 'video_avatar',
                    'type' => 'video',
                    'names' => ['hd' => $name],
                ]);
            }
        });
});
```

3) Poster (opcjonalny, ale zalecany) (Dzień 0–1)
- Jeśli posiadasz obrazy pierwszej klatki: utwórz dla każdego content rekord kind = 'video_poster', type = 'image' w cms_content_files przy pomocy Media::uploadModelImage($content, $file, 'video_poster').
- Jeśli nie masz posterów — etap można pominąć lub wygenerować je w przyszłości.

4) Okres przejściowy (Dzień 1–X)
- W aplikacji produkcyjnej używaj już tylko nowych accessorów i danych:
  - Odczyt URL wideo: $content->video_avatar_path (zwraca rozmiar wg display, domyślnie 'hd').
  - Odczyt posteru: $content->video_poster_path.
  - Listy plików: $content->videoFilesList() (type = 'video'), $content->imageFilesList() (type = 'image').
- Zachowaj istniejącą relację Content->video() jako fallback (dla pełnej zgodności wstecznej) na czas przejściowy.
- Nowe zapisy wideo kieruj WYŁĄCZNIE do ContentFile przez Media::uploadModelVideos.

5) Deprecjacja API (Dzień X)
- Zaktualizuj kod aplikacji:
  - PRZESTAŃ używać: $content->video_path i $content->video (relacja).
  - Zastąp przez: $content->video_avatar_path oraz $content->files()->where('kind','video_avatar')->first().
- Opcjonalnie dodaj ostrzeżenia/deprecation notice w kodzie aplikacyjnym (niekoniecznie w pakiecie) jeśli nadal ktoś odwołuje się do legacy API.

6) Usunięcie ContentVideo (Dzień X+1)
- Upewnij się, że od co najmniej 1 cyklu wydawniczego nie ma wywołań legacy API.
- Usuń w swojej aplikacji zależności od ContentVideo: zapytania, seedy, form requesty, kontrolery.
- W tym pakiecie w kolejnym wydaniu można:
  - usunąć model Dominservice\\LaravelCms\\Models\\ContentVideo,
  - usunąć relację Content::video() i accessor getVideoPathAttribute(),
  - usunąć klucz tabeli 'content_video' z configu,
  - dodać migration drop table cms_content_videos (jeśli tabela jest własnością pakietu i nie jest używana gdzie indziej).

Weryfikacja po migracji
- Sprawdzanie spójności rekordów:
```sql
-- treści posiadające legacy video bez nowego wpisu w files
SELECT v.content_uuid
FROM cms_content_videos v
LEFT JOIN cms_content_files f
  ON f.content_uuid = v.content_uuid AND f.kind = 'video_avatar' AND f.deleted_at IS NULL
WHERE f.uuid IS NULL;
```
- Sprawdź poprawność URL:
  - Dla kilku rekordów pobierz $content->video_avatar_path i zweryfikuj, że plik istnieje na dysku config('cms.disks.content_video').
- Testy E2E/aplikacyjne:
  - Widok listy i detali Content wyświetla właściwy plik wideo/poster.
  - Upload nowych wideo trafia do cms_content_files, a nie do cms_content_videos.

Rollback (awaryjnie)
- Jeśli po backfillu zauważysz problemy:
  - Możesz tymczasowo wrócić do accessorów opartych o ContentVideo (video_path), ponieważ pliki fizyczne nie zostały ruszone.
  - Usuń lub soft-delete rekordy 'video_avatar' w cms_content_files, o ile to konieczne.
  - Przywróć kopię bazy i/lub storage z backupu wykonanym w etapie 1.

Checklist zmian w aplikacji (poza pakietem)
- [ ] Wszystkie miejsca używające $content->video_path zrefaktoryzowane do $content->video_avatar_path.
- [ ] Zapisy nowych wideo korzystają z Media::uploadModelVideos.
- [ ] W widokach/posterach użyty $content->video_poster_path (jeśli wymagane).
- [ ] Monitoring 404 dla wideo — brak.
- [ ] Feature toggles/konfiguracja wyłączająca stare API — wdrożona.

Notatki
- W tym pakiecie pozostawiono ContentVideo dla kompatybilności. Plan zakłada jego usunięcie w kolejnym głównym wydaniu po okresie przejściowym. Jeśli chcesz, możesz przyspieszyć usunięcie w swoim forku/projekcie, stosując się do listy w punkcie 6.
