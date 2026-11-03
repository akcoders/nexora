import 'package:dio/dio.dart';

import 'library_models.dart';

/// Knowledge library endpoints (`/api/v1/library*`).
class LibraryApi {
  const LibraryApi(this.dio);

  final Dio dio;

  static const perPage = 20;

  Future<LibraryFilterOptions> filters() async {
    final response = await dio.get('library/filters');
    return LibraryFilterOptions.fromJson(response.data);
  }

  Future<LibraryPage> guides(LibraryQuery query, {int page = 1}) async {
    final response = await dio.get(
      'library',
      queryParameters: {
        ...query.toParameters(),
        'page': page,
        'per_page': perPage,
      },
    );
    return LibraryPage.fromJson(response.data);
  }

  Future<LibraryArticle> guide(int id) async {
    final response = await dio.get('library/$id');
    return LibraryArticle.fromJson(response.data);
  }
}

/// True when the server refused because the user lacks `library.read`.
bool isLibraryForbidden(Object error) =>
    error is DioException && error.response?.statusCode == 403;

/// True when the guide no longer exists (deleted or unpublished).
bool isLibraryNotFound(Object error) =>
    error is DioException && error.response?.statusCode == 404;
