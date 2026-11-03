import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

/// Nexora classic palette, shared with the web admin (resources/css/theme.css).
class AppColors {
  static const primary = Color(0xFF1F5FBF);
  static const primaryDark = Color(0xFF194F9F);
  static const primarySoft = Color(0xFFE8F0FC);
  static const secondary = Color(0xFF3B78D8);
  static const accent = Color(0xFF0F8B8D);

  static const navy = Color(0xFF16233F);
  static const navy2 = Color(0xFF1B2A4A);
  static const navyText = Color(0xFFB9C4D8);
  static const navyMuted = Color(0xFF6F7F9D);
  static const navyActive = Color(0xFF4F8CFF);

  static const heading = Color(0xFF0F172A);
  static const ink = Color(0xFF1E293B);
  static const muted = Color(0xFF64748B);
  static const subtle = Color(0xFF94A3B8);
  static const background = Color(0xFFF3F5F8);
  static const surface = Colors.white;
  static const surface2 = Color(0xFFF8FAFC);
  static const border = Color(0xFFE1E6EE);
  static const borderStrong = Color(0xFFCFD7E3);

  static const success = Color(0xFF15803D);
  static const successSoft = Color(0xFFE3F5E9);
  static const warning = Color(0xFFB45309);
  static const warningSoft = Color(0xFFFDF1DC);
  static const danger = Color(0xFFB91C1C);
  static const dangerSoft = Color(0xFFFDE8E8);
  static const info = Color(0xFF0369A1);
  static const infoSoft = Color(0xFFE0F1FB);
  static const purple = Color(0xFF6D28D9);
  static const purpleSoft = Color(0xFFEFE9FC);
  static const orange = Color(0xFFC2410C);
  static const orangeSoft = Color(0xFFFDEADF);
  static const slate = Color(0xFF475569);
  static const slateSoft = Color(0xFFEEF1F5);
}

/// Corner radii: the classic look keeps corners tight.
class AppRadius {
  static const sm = 6.0;
  static const md = 8.0;
  static const lg = 12.0;
}

class AppTheme {
  const AppTheme._();

  static const overlay = SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
    statusBarBrightness: Brightness.light,
    systemNavigationBarColor: Colors.white,
    systemNavigationBarIconBrightness: Brightness.dark,
  );

  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      primary: AppColors.primary,
      onPrimary: Colors.white,
      secondary: AppColors.secondary,
      tertiary: AppColors.accent,
      error: AppColors.danger,
      surface: AppColors.surface,
      onSurface: AppColors.ink,
      surfaceContainerHighest: AppColors.surface2,
      outline: AppColors.borderStrong,
      outlineVariant: AppColors.border,
      brightness: Brightness.light,
    );
    final base = GoogleFonts.interTextTheme().apply(
      bodyColor: AppColors.ink,
      displayColor: AppColors.heading,
    );
    final text = base.copyWith(
      headlineMedium: base.headlineMedium?.copyWith(
        fontSize: 24,
        fontWeight: FontWeight.w700,
        letterSpacing: -.3,
      ),
      headlineSmall: base.headlineSmall?.copyWith(
        fontSize: 20,
        fontWeight: FontWeight.w700,
        letterSpacing: -.2,
      ),
      titleLarge: base.titleLarge?.copyWith(
        fontSize: 18,
        fontWeight: FontWeight.w700,
      ),
      titleMedium: base.titleMedium?.copyWith(
        fontSize: 15,
        fontWeight: FontWeight.w700,
      ),
      titleSmall: base.titleSmall?.copyWith(
        fontSize: 13,
        fontWeight: FontWeight.w600,
      ),
      bodyLarge: base.bodyLarge?.copyWith(fontSize: 15),
      bodyMedium: base.bodyMedium?.copyWith(fontSize: 14),
      bodySmall: base.bodySmall?.copyWith(fontSize: 12, color: AppColors.muted),
      labelLarge: base.labelLarge?.copyWith(
        fontSize: 14,
        fontWeight: FontWeight.w600,
      ),
    );
    final inputBorder = OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadius.md),
      borderSide: const BorderSide(color: AppColors.borderStrong),
    );
    final buttonShape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadius.md),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.background,
      textTheme: text,
      splashFactory: InkRipple.splashFactory,
      visualDensity: VisualDensity.standard,
      dividerColor: AppColors.border,
      dividerTheme: const DividerThemeData(
        color: AppColors.border,
        thickness: 1,
        space: 1,
      ),
      appBarTheme: AppBarTheme(
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 4,
        toolbarHeight: 58,
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        foregroundColor: AppColors.heading,
        systemOverlayStyle: overlay,
        shape: const Border(bottom: BorderSide(color: AppColors.border)),
        iconTheme: const IconThemeData(color: AppColors.ink, size: 21),
        actionsIconTheme: const IconThemeData(color: AppColors.ink, size: 20),
        titleTextStyle: GoogleFonts.inter(
          color: AppColors.heading,
          fontSize: 17,
          fontWeight: FontWeight.w700,
          letterSpacing: -.2,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: const BorderSide(color: AppColors.border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        labelStyle: const TextStyle(color: AppColors.muted, fontSize: 14),
        floatingLabelStyle: const TextStyle(
          color: AppColors.primary,
          fontWeight: FontWeight.w600,
        ),
        hintStyle: const TextStyle(color: AppColors.subtle, fontSize: 14),
        helperStyle: const TextStyle(color: AppColors.muted, fontSize: 12),
        prefixIconColor: AppColors.muted,
        suffixIconColor: AppColors.muted,
        border: inputBorder,
        enabledBorder: inputBorder,
        disabledBorder: inputBorder.copyWith(
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: inputBorder.copyWith(
          borderSide: const BorderSide(color: AppColors.primary, width: 1.6),
        ),
        errorBorder: inputBorder.copyWith(
          borderSide: const BorderSide(color: AppColors.danger),
        ),
        focusedErrorBorder: inputBorder.copyWith(
          borderSide: const BorderSide(color: AppColors.danger, width: 1.6),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(64, 48),
          padding: const EdgeInsets.symmetric(horizontal: 20),
          shape: buttonShape,
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          disabledBackgroundColor: AppColors.border,
          disabledForegroundColor: AppColors.subtle,
          textStyle: GoogleFonts.inter(
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          minimumSize: const Size(64, 48),
          shape: buttonShape,
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(64, 46),
          padding: const EdgeInsets.symmetric(horizontal: 16),
          shape: buttonShape,
          foregroundColor: AppColors.ink,
          side: const BorderSide(color: AppColors.borderStrong),
          textStyle: GoogleFonts.inter(
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primary,
          shape: buttonShape,
          padding: const EdgeInsets.symmetric(horizontal: 12),
          textStyle: GoogleFonts.inter(
            fontSize: 13.5,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: AppColors.ink,
          shape: buttonShape,
        ),
      ),
      segmentedButtonTheme: SegmentedButtonThemeData(
        style: SegmentedButton.styleFrom(
          shape: buttonShape,
          side: const BorderSide(color: AppColors.borderStrong),
          selectedBackgroundColor: AppColors.primarySoft,
          selectedForegroundColor: AppColors.primary,
          foregroundColor: AppColors.ink,
          textStyle: GoogleFonts.inter(
            fontSize: 13,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: Colors.white,
        selectedColor: AppColors.primarySoft,
        disabledColor: AppColors.surface2,
        side: const BorderSide(color: AppColors.border),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        labelStyle: GoogleFonts.inter(
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          color: AppColors.ink,
        ),
        secondaryLabelStyle: GoogleFonts.inter(
          fontSize: 12.5,
          fontWeight: FontWeight.w600,
          color: AppColors.primary,
        ),
        checkmarkColor: AppColors.primary,
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        showCheckmark: false,
      ),
      tabBarTheme: TabBarThemeData(
        indicatorSize: TabBarIndicatorSize.label,
        dividerColor: AppColors.border,
        dividerHeight: 1,
        indicator: const UnderlineTabIndicator(
          borderSide: BorderSide(color: AppColors.primary, width: 2.5),
          borderRadius: BorderRadius.vertical(top: Radius.circular(2)),
        ),
        labelColor: AppColors.primary,
        unselectedLabelColor: AppColors.muted,
        labelStyle: GoogleFonts.inter(
          fontWeight: FontWeight.w700,
          fontSize: 13.5,
        ),
        unselectedLabelStyle: GoogleFonts.inter(
          fontWeight: FontWeight.w500,
          fontSize: 13.5,
        ),
        labelPadding: const EdgeInsets.symmetric(horizontal: 14),
        overlayColor: WidgetStateProperty.all(
          AppColors.primary.withValues(alpha: .06),
        ),
        tabAlignment: TabAlignment.start,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        indicatorColor: AppColors.primarySoft,
        indicatorShape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        elevation: 0,
        height: 66,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => GoogleFonts.inter(
            fontSize: 11.5,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w500,
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : AppColors.muted,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            size: 21,
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : AppColors.muted,
          ),
        ),
      ),
      drawerTheme: const DrawerThemeData(
        backgroundColor: AppColors.navy,
        surfaceTintColor: Colors.transparent,
        width: 300,
        shape: RoundedRectangleBorder(),
        endShape: RoundedRectangleBorder(),
      ),
      listTileTheme: ListTileThemeData(
        iconColor: AppColors.muted,
        textColor: AppColors.ink,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16),
        minLeadingWidth: 22,
        horizontalTitleGap: 12,
        titleTextStyle: GoogleFonts.inter(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.ink,
        ),
        subtitleTextStyle: GoogleFonts.inter(
          fontSize: 12.5,
          color: AppColors.muted,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 6,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        titleTextStyle: GoogleFonts.inter(
          fontSize: 17,
          fontWeight: FontWeight.w700,
          color: AppColors.heading,
        ),
        contentTextStyle: GoogleFonts.inter(
          fontSize: 14,
          height: 1.5,
          color: AppColors.ink,
        ),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: Colors.white,
        showDragHandle: true,
        dragHandleColor: AppColors.borderStrong,
        dragHandleSize: Size(40, 4),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.heading,
        contentTextStyle: GoogleFonts.inter(
          color: Colors.white,
          fontSize: 13.5,
          fontWeight: FontWeight.w500,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        insetPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
        linearTrackColor: AppColors.primarySoft,
        circularTrackColor: Colors.transparent,
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? Colors.white
              : AppColors.subtle,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AppColors.primary
              : AppColors.slateSoft,
        ),
        trackOutlineColor: WidgetStateProperty.all(Colors.transparent),
      ),
      checkboxTheme: CheckboxThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
        side: const BorderSide(color: AppColors.borderStrong, width: 1.5),
      ),
      popupMenuTheme: PopupMenuThemeData(
        color: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          side: const BorderSide(color: AppColors.border),
        ),
      ),
      datePickerTheme: DatePickerThemeData(
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        headerBackgroundColor: AppColors.navy,
        headerForegroundColor: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
      ),
      timePickerTheme: TimePickerThemeData(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: AppColors.heading,
          borderRadius: BorderRadius.circular(AppRadius.sm),
        ),
        textStyle: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      badgeTheme: const BadgeThemeData(
        backgroundColor: AppColors.danger,
        textColor: Colors.white,
        textStyle: TextStyle(fontSize: 10, fontWeight: FontWeight.w700),
      ),
    );
  }
}
