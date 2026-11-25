---
view: components.packages.quick-look
title: A quick look
subtitle: A paginated resource collection with a message, metadata and a notification.
file: app/Http/Controllers/PostController.php
language: php
code: |
  use App\Http\Resources\PostResource;
  use App\Post;
  use Pharaonic\Laravel\ApiResponse\Response;

  public function index()
  {
      return Response::success(PostResource::collection(Post::query()->paginate(15)))
          ->message('Posts retrieved successfully.')
          ->meta('version', '1.0')
          ->notify('Posts loaded.', 'success')
          ->withQueryString(['search', 'sort']);
  }
---
