<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('cms_categories') ) {
            Schema::create('cms_categories', function (Blueprint $table) {
                $table->uuid('uuid')->primary();
                $table->string('type'); // section | block | faq |...
                $table->integer('parent_id')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('cms_category_translations') ) {
            Schema::create('cms_category_translations', function (Blueprint $table) {
                $table->id();
                $table->uuid('category_uuid');
                $table->foreign('category_uuid')
                    ->references('uuid')
                    ->on('cms_categories')
                    ->onDelete('cascade');
                $table->string('locale')->index();
                $table->string('slug');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('meta_title')->nullable();
                $table->string('meta_keywords')->nullable();
                $table->string('meta_description')->nullable();
                $table->unique(['category_uuid','locale']);
            });
        }

        if (!Schema::hasTable('cms_articles') ) {
            Schema::create('cms_articles', function (Blueprint $table) {
                $table->uuid('uuid')->primary();
                $table->string('type'); // section | block | faq |...
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('cms_article_translations') ) {
            Schema::create('cms_article_translations', function (Blueprint $table) {
                $table->id();
                $table->uuid('article_uuid');
                $table->foreign('article_uuid')
                    ->references('uuid')
                    ->on('cms_articles')
                    ->onDelete('cascade');
                $table->string('locale')->index();
                $table->string('slug');
                $table->string('name');
                $table->string('sub_name')->nullable();
                $table->text('description');
                $table->string('meta_title')->nullable();
                $table->string('meta_keywords')->nullable();
                $table->string('meta_description')->nullable();
                $table->unique(['article_uuid','locale']);
            });
        }

        if (!Schema::hasTable('cms_article_categories') ) {
            Schema::create('cms_article_categories', function (Blueprint $table) {
                $table->id();
                $table->uuid('article_uuid');
                $table->foreign('article_uuid')
                    ->references('uuid')
                    ->on('cms_articles')
                    ->onDelete('cascade');
                $table->uuid('category_uuid');
                $table->foreign('category_uuid')
                    ->references('uuid')
                    ->on('cms_categories')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cms_article_categories');
        Schema::drop('cms_article_translations');
        Schema::drop('cms_articles');
        Schema::drop('cms_category_translations');
        Schema::drop('cms_categories');
    }
};