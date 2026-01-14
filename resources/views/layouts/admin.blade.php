@php($mode = config('cms.admin.layout.mode', 'package'))
@php($layoutView = config('cms.admin.layout.view', 'cms::layouts.bootstrap'))
@php($layoutSection = config('cms.admin.layout.section', 'content'))
@php($component = config('cms.admin.layout.component', 'admin-layout'))

@if($mode === 'component')
    <x-dynamic-component :component="$component">
        @yield('content')
    </x-dynamic-component>
@else
    @extends($layoutView)

    @section($layoutSection)
        @yield('content')
    @endsection
@endif
