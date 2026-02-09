import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  // 🎨 COLORS
  // 🎨 COLORS - NISIS  Branding
  static const Color primaryBlue = Color(0xFF003D7C); // NISIS Deep Blue
  static const Color lightBlue = Color(0xFF0056b3);   // Slightly lighter Blue
  static const Color accentTeal = Color(0xFFF7941D);  // NISIS Orange (Replacing Teal)
  static const Color darkBg = Color(0xFF001f3f);      // Very Dark Blue (almost black)
  static const Color cardBg = Color(0xFF003366);      // Dark Blue Card
  static const Color surfaceWhite = Color(0xFFFFFFFF);
  static const Color primaryPurple = Color(0xFFFF6600); // Reddish Orange (Replacing Purple)

  // 🌈 GRADIENTS
  static const LinearGradient primaryGradient = LinearGradient(
    colors: [primaryBlue, accentTeal], // Blue to Orange
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
  
  static const LinearGradient bgGradient = LinearGradient(
    colors: [Color(0xFF001f3f), Color(0xFF003D7C)], // Dark Blue to Logo Blue
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
  );

  // ✍️ TEXT STYLES (Google Fonts)
  static TextStyle get titleStyle => GoogleFonts.poppins(
    fontSize: 28,
    fontWeight: FontWeight.bold,
    color: surfaceWhite,
    letterSpacing: 1.2,
  );

  static TextStyle get subtitleStyle => GoogleFonts.poppins(
    fontSize: 16,
    color: Colors.white70,
  );

  static TextStyle get buttonText => GoogleFonts.poppins(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    color: Colors.white,
  );

  // 🖼️ INPUT DECORATION
  static InputDecoration inputDecoration(String label, IconData icon) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: Colors.white70),
      prefixIcon: Icon(icon, color: Colors.white70),
      filled: true,
      fillColor: Colors.white.withValues(alpha: 0.1), // Glassmorphism
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.1)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(15),
        borderSide: const BorderSide(color: accentTeal),
      ),
    );
  }
}
