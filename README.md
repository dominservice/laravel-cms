# Dominservice Laravel CMS

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
  - Kolumny: uuid, *_uuid, kind ('avatar' lub 'additional' itp.), type (opcjonalny pod-typ), names (JSON: mapa rozmiar => nazwa pliku), timestamps, softDeletes

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