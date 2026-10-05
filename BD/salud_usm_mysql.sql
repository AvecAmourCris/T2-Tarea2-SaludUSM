-- ============================================================
-- SaludUSM - Sistema de Gestión Clínica (Tarea 2, MySQL)
-- Base de datos normalizada a 3FN
-- Orden del script: tablas -> datos -> funciones -> procedimientos
--                   -> triggers -> views
-- (los triggers se crean DESPUÉS de cargar los datos de prueba)
-- ATENCIÓN: borra y recrea la base 'salud_usm' si ya existe.
-- ============================================================
DROP DATABASE IF EXISTS salud_usm;
CREATE DATABASE salud_usm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE salud_usm;
SET NAMES utf8mb4;

-- ---------- Catálogos (tablas de dominio) ----------
CREATE TABLE Prevision (
    ID_Prevision INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE Especialidad (
    ID_Especialidad INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE Estado_Cita (
    ID_Estado_Cita INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE Rol (
    ID_Rol INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------- Geografía (elimina la dependencia transitiva Centro -> Comuna -> Región) ----------
CREATE TABLE Region (
    ID_Region INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE Comuna (
    ID_Comuna INT AUTO_INCREMENT PRIMARY KEY,
    Nombre VARCHAR(50) NOT NULL UNIQUE,
    ID_Region INT NOT NULL,
    FOREIGN KEY (ID_Region) REFERENCES Region(ID_Region)
) ENGINE=InnoDB;

CREATE TABLE Centro_Medico (
    Codigo_Centro VARCHAR(20) PRIMARY KEY,
    Nombre VARCHAR(100) NOT NULL,
    ID_Comuna INT NOT NULL,
    FOREIGN KEY (ID_Comuna) REFERENCES Comuna(ID_Comuna)
) ENGINE=InnoDB;

-- ---------- Cuentas de acceso (login por RUT) ----------
CREATE TABLE Usuario (
    ID_Usuario INT AUTO_INCREMENT PRIMARY KEY,
    RUT VARCHAR(10) NOT NULL UNIQUE,
    Password_Hash VARCHAR(255) NOT NULL,
    ID_Rol INT NOT NULL,
    FOREIGN KEY (ID_Rol) REFERENCES Rol(ID_Rol),
    CONSTRAINT chk_usuario_rut CHECK (RUT REGEXP '^[0-9]{7,8}-[0-9Kk]$')
) ENGINE=InnoDB;

-- ---------- Personas (su RUT es PK y a la vez FK a Usuario: borrar la cuenta borra el perfil) ----------
CREATE TABLE Paciente (
    RUT_Paciente VARCHAR(10) PRIMARY KEY,
    Nombre_Completo VARCHAR(150) NOT NULL,
    Fecha_Nacimiento DATE NOT NULL,
    Sexo ENUM('M','F','Otro') NOT NULL,
    Telefono VARCHAR(20) NULL,
    Email VARCHAR(100) NOT NULL UNIQUE,
    ID_Comuna_Residencia INT NOT NULL,
    ID_Prevision INT NOT NULL,
    FOREIGN KEY (RUT_Paciente) REFERENCES Usuario(RUT) ON DELETE CASCADE,
    FOREIGN KEY (ID_Comuna_Residencia) REFERENCES Comuna(ID_Comuna),
    FOREIGN KEY (ID_Prevision) REFERENCES Prevision(ID_Prevision)
) ENGINE=InnoDB;

CREATE TABLE Medico (
    RUT_Medico VARCHAR(10) PRIMARY KEY,
    Nombre_Completo VARCHAR(150) NOT NULL,
    Email_Institucional VARCHAR(100) NOT NULL UNIQUE,
    FOREIGN KEY (RUT_Medico) REFERENCES Usuario(RUT) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Medico_Especialidad (
    RUT_Medico VARCHAR(10) NOT NULL,
    ID_Especialidad INT NOT NULL,
    PRIMARY KEY (RUT_Medico, ID_Especialidad),
    FOREIGN KEY (RUT_Medico) REFERENCES Medico(RUT_Medico) ON DELETE CASCADE,
    FOREIGN KEY (ID_Especialidad) REFERENCES Especialidad(ID_Especialidad) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Medico_Centro (
    RUT_Medico VARCHAR(10) NOT NULL,
    Codigo_Centro VARCHAR(20) NOT NULL,
    PRIMARY KEY (RUT_Medico, Codigo_Centro),
    FOREIGN KEY (RUT_Medico) REFERENCES Medico(RUT_Medico) ON DELETE CASCADE,
    FOREIGN KEY (Codigo_Centro) REFERENCES Centro_Medico(Codigo_Centro) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Citas y atención clínica ----------
CREATE TABLE Cita (
    ID_Cita INT AUTO_INCREMENT PRIMARY KEY,
    Fecha_Hora DATETIME NOT NULL,
    RUT_Paciente VARCHAR(10) NOT NULL,
    RUT_Medico VARCHAR(10) NOT NULL,
    Codigo_Centro VARCHAR(20) NOT NULL,
    ID_Especialidad INT NOT NULL,
    ID_Estado_Cita INT NOT NULL,
    FOREIGN KEY (RUT_Paciente) REFERENCES Paciente(RUT_Paciente) ON DELETE CASCADE,
    FOREIGN KEY (RUT_Medico) REFERENCES Medico(RUT_Medico),
    FOREIGN KEY (Codigo_Centro) REFERENCES Centro_Medico(Codigo_Centro),
    FOREIGN KEY (ID_Especialidad) REFERENCES Especialidad(ID_Especialidad),
    FOREIGN KEY (ID_Estado_Cita) REFERENCES Estado_Cita(ID_Estado_Cita),
    INDEX idx_cita_medico_fecha (RUT_Medico, Fecha_Hora),
    INDEX idx_cita_paciente_fecha (RUT_Paciente, Fecha_Hora)
) ENGINE=InnoDB;

CREATE TABLE Atencion (
    ID_Atencion INT AUTO_INCREMENT PRIMARY KEY,
    Motivo_Consulta VARCHAR(500) NOT NULL,
    Observaciones_Clinicas TEXT NOT NULL,
    ID_Cita INT NOT NULL UNIQUE,
    FOREIGN KEY (ID_Cita) REFERENCES Cita(ID_Cita) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Diagnostico (
    Codigo_CIE10 VARCHAR(10) PRIMARY KEY,
    Descripcion VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE Atencion_Diagnostico (
    ID_Atencion INT NOT NULL,
    Codigo_CIE10 VARCHAR(10) NOT NULL,
    PRIMARY KEY (ID_Atencion, Codigo_CIE10),
    FOREIGN KEY (ID_Atencion) REFERENCES Atencion(ID_Atencion) ON DELETE CASCADE,
    FOREIGN KEY (Codigo_CIE10) REFERENCES Diagnostico(Codigo_CIE10) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Receta (
    ID_Receta INT AUTO_INCREMENT PRIMARY KEY,
    Medicamento VARCHAR(100) NOT NULL,
    Dosis VARCHAR(255) NOT NULL,
    Dias_Tratamiento INT NOT NULL,
    ID_Atencion INT NOT NULL,
    FOREIGN KEY (ID_Atencion) REFERENCES Atencion(ID_Atencion) ON DELETE CASCADE,
    CONSTRAINT chk_receta_dias CHECK (Dias_Tratamiento > 0)
) ENGINE=InnoDB;


-- ============================================================
-- DATOS DE PRUEBA
-- ============================================================

INSERT INTO Prevision (Nombre) VALUES ('Fonasa'), ('Isapre'), ('Particular');

INSERT INTO Estado_Cita (Nombre) VALUES ('Reservada'), ('Confirmada'), ('Atendida'), ('No Asistió'), ('Cancelada');

INSERT INTO Rol (Nombre) VALUES ('Paciente'), ('Médico'), ('Administrador');

INSERT INTO Especialidad (Nombre) VALUES
    ('Medicina General'),
    ('Medicina Interna'),
    ('Pediatría'),
    ('Cirugía General'),
    ('Obstetricia y Ginecología'),
    ('Psiquiatría Adultos'),
    ('Psiquiatría Pediátrica y de la Adolescencia'),
    ('Cardiología'),
    ('Dermatología'),
    ('Neurología Adultos'),
    ('Neurología Pediátrica'),
    ('Oftalmología'),
    ('Otorrinolaringología'),
    ('Traumatología y Ortopedia'),
    ('Urología'),
    ('Anestesiología'),
    ('Medicina Familiar'),
    ('Oncología Médica'),
    ('Endocrinología Adultos'),
    ('Gastroenterología Adultos'),
    ('Nefrología Adultos'),
    ('Reumatología'),
    ('Geriatría'),
    ('Infectología'),
    ('Inmunología'),
    ('Medicina Intensiva Adultos'),
    ('Enfermedades Respiratorias Adultos'),
    ('Anatomía Patológica'),
    ('Radiología'),
    ('Cirugía Pediátrica'),
    ('Cirugía Plástica y Reparadora'),
    ('Neurocirugía'),
    ('Cirugía Cardiovascular'),
    ('Cirugía de Tórax'),
    ('Medicina de Urgencia');

INSERT INTO Region (Nombre) VALUES
    ('Región de Valparaíso'),
    ('Región Metropolitana');

INSERT INTO Comuna (ID_Comuna, Nombre, ID_Region) VALUES
    (1, 'Valparaíso', 1),
    (2, 'Viña del Mar', 1),
    (3, 'Quilpué', 1),
    (4, 'Villa Alemana', 1),
    (5, 'Santiago', 2),
    (6, 'Providencia', 2),
    (7, 'Ñuñoa', 2),
    (8, 'Maipú', 2),
    (9, 'Puente Alto', 2),
    (10, 'San Bernardo', 2),
    (11, 'La Florida', 2),
    (12, 'Las Condes', 2);

INSERT INTO Centro_Medico (Codigo_Centro, Nombre, ID_Comuna) VALUES
    ('CM-VAL-01', 'Centro Médico Valparaíso Centro', 1),
    ('CM-VIN-01', 'Centro Médico Viña del Mar', 2),
    ('CM-STG-01', 'Centro Médico Santiago Centro', 5),
    ('CM-PRO-01', 'Centro Médico Providencia', 6);

-- Cuentas de acceso (contraseñas en bcrypt). Ver README para las credenciales de prueba.
INSERT INTO Usuario (RUT, Password_Hash, ID_Rol) VALUES
    ('11111111-1', '$2b$10$S8nqceNlEKyh86Hjw5/NROMPDAdZKrapJ5v7JXijhkTLj/c/Ui48K', 3),
    ('9664386-1', '$2b$10$7tTgRPkoPlnK3S486aWCNuMTjQ50/kQa3MEYDjGu2f6Mw7EJw9uCO', 2),
    ('9620344-6', '$2b$10$/TCisRCiGORyOi5Ye6qFY.d9VdKXf0SvD8.9VNJ1Kyfbmgt2aduLO', 2),
    ('9269774-6', '$2b$10$zdxBAT2GqtQorZQe3JckG.kQkhX8c6SGOI26CXD4.w1tapmijSCtC', 2),
    ('9548556-1', '$2b$10$Pa802CvwuPkgMqeeD2XZaeNPq/Zb6PEpXey7rtR9jT4KI3c6wOvzq', 2),
    ('9561704-2', '$2b$10$yRMKjkG0nO6abvt8dOKuQOY/FW9BE2g74a.C7fXy4arEXARB/LkBe', 2),
    ('9067300-9', '$2b$10$t1pQoIZIOdTSs/PjbvB2EedfrpKkM5O7D00V.pjXZl14BYC4G1DLi', 2),
    ('9873251-9', '$2b$10$71ORzhhaCu7lej50kgPlW.elJP8YXr/c0s3d.d/8xWYQo6Jy7/W2i', 2),
    ('9814665-2', '$2b$10$OzqpsDq7LmNwr1H/IkA8duR1.UT7.p7IgJcM1nKBLTNPi.1v22tce', 2),
    ('9745702-6', '$2b$10$wTMMabLz3ti17Cb6thH/POcTSxUjaS1ROOpf7XdVjZ4nk49FDjvly', 2),
    ('9378098-1', '$2b$10$9ZTyPo63knH4qWzQxXc.jOqzSshEM50XOGk4HjBh98O8axtxo/spK', 2),
    ('9992053-K', '$2b$10$2VyXtb22idtoVdWdWFoYNeh31q4kQa29duhXFvrjxixK1aA4zFbHe', 2),
    ('9188995-1', '$2b$10$y7Pki5Y7x565z8ST3w/COO5q97I7aJMWHRaf0GFGrFonWqSGjPPAa', 2),
    ('9600077-4', '$2b$10$.CWhaMVC.ewkKiYcyHSMPOhyuArZj9yu3E4WZxk7o8yNHRP9VbEta', 2),
    ('9454803-9', '$2b$10$DtDIp2tFuD/FAkBwSfH5HOAqKqYFXp1I1lxOZjL.C.NqIgz8X21wu', 2),
    ('9318463-7', '$2b$10$6e476m0lBdTdlUcsJZ7iMOJxwnQurRqJyAhsRNBuykrAG254mihIW', 2),
    ('8539253-0', '$2b$10$0C.QaEgVRt8ffP1a/v7wi.bCmfn.k551z2f59sAvoA/UHrumvZFbi', 1),
    ('8836544-5', '$2b$10$gzPkeqnxrn81skBb/7vEFe/EKfFz3CMfj/fwbwar/fwnS3vNCMwXG', 1),
    ('8846765-5', '$2b$10$dM1Q7t4h8Dp1Y08bKuXATu6Ib9w2AvM64uvBzi2XZjxB4xLSdAGLe', 1),
    ('8253074-6', '$2b$10$D.B3zZZu1zZRo0vOqb9I6O3CyAF.O9Ncj6k7LcKcbc3GV91luMIPm', 1),
    ('8402929-7', '$2b$10$GBZz2K3eoZxF8SrhHl0qRO/.0OdgOnmwUvQsxblh5tneyYAiJtDcK', 1),
    ('8306546-K', '$2b$10$6mnYp/OHEjMD6p0z5MYALOJaUJ8Q8/V2neeRm.hp8rhm9A.fg3Cgy', 1),
    ('8163779-2', '$2b$10$23eClKTJnZA.tWDOzq82QuG1cTLtKib9Kw0H79EBp/w3ENi4Bfcaq', 1),
    ('8919172-6', '$2b$10$GcJrBehsziBlHjR0Lh4a2uKiPVf5DOJPyDlG4oX0Nlsgg11PrUn..', 1),
    ('8185951-5', '$2b$10$g/goJiftF3F2idLBNu/4MeYy95PfMaMLOtcpNoYRvb9PNt/0W8Mwe', 1),
    ('8460385-6', '$2b$10$DE6aAxFweD1RBHk0GZ0JKu4I4UtG.pRdSW6ITxgEt4fzQLpRGYYAW', 1),
    ('8415050-9', '$2b$10$mKKwl3ZsGXPJV0UxfAmrnuGWWS309gluwmPX3Z9xo1ljqm3HLfyhq', 1),
    ('8768980-8', '$2b$10$BVOMl/3QkelSPl4OLRmJ7OGvYEGhJP4dSGEWkDVLa1HRHphLa8nxu', 1),
    ('8677381-3', '$2b$10$Z6uFKTtviMhI635zwYtck.a/PHs1HKAonfX037ujG6lWFichifdLS', 1),
    ('8655594-8', '$2b$10$/990K0PrLaPgWFDIHGlv4e3tCK0RbIp.465OjUy/T5VU/jhCEwSBi', 1),
    ('8410968-1', '$2b$10$k06AOzEx/K/azR71teDeGOtbXJKnqrdPnrQ1GOcuWQVRJ79WiAkz2', 1),
    ('8262779-0', '$2b$10$g6jDuRV6gjQKzMEMDLo3j.92tmjYip/UGDRQtkpD/vfiCzyChpx/W', 1),
    ('8732159-2', '$2b$10$/kMHFI/v2i03FAbjbpjizObb/m3R42fv7bC.bK8AqoHJ33HgTmqpG', 1),
    ('8297342-7', '$2b$10$9Q92zPrB4rl3.MZvFDkvqOkxysQjEjPP2vrsusP1Uq92zYQVEfgWa', 1),
    ('8452753-K', '$2b$10$6Fj3pS6H6xkx5o7Az..jc.EU2C4A4qymYVm1oKbe50.ZbPKGxgSjS', 1),
    ('8288495-5', '$2b$10$vyOD9goL8xqP6xKOgruAB.O4uznNQzbb0CSsXDjTww23gzq3T/a6S', 1),
    ('8983368-K', '$2b$10$134BmA9iSRcyUAqV3vDKxu645GQDbOOVX87cPqLG5mY5ugWK6v0eW', 1),
    ('8140795-9', '$2b$10$KpBl6GGBG5oyIg1A9XzsMeoWZvVo9acV1YlHvueXC9By90HP7C/0C', 1),
    ('8887895-7', '$2b$10$6pVuD3gQMhmNzInToMxXI.nOnJYOrK16v4Ls84hE5T9ITDGYnebJm', 1),
    ('8153041-6', '$2b$10$Xn.xYOzNXzKIsiOuLgp6gen5eYSZa7kVF3ZV5blX1Z7GJoKu4Qnmi', 1),
    ('8132763-7', '$2b$10$dlcbPE2pIcB8vG0QUeHfDeJ5ugaHCvyv5W30nU8MLId4wMuJAAzwu', 1),
    ('8220479-2', '$2b$10$3TL8zaeY5Pyh7q2S5IYsg.9vWul3HwSGB9Emf8VvUXavRJnDrZoeC', 1),
    ('8107952-8', '$2b$10$Ul97JnGalVRaJq5AbO7uKeeBQV8qFust8zMiNpPDEedk7yPudYhy6', 1),
    ('8376054-0', '$2b$10$NAxX1LnaeOh0j0DjbamjQuAdqm1rbUjxFVaiI3dQrndtcY1Y0m3N6', 1),
    ('8802216-5', '$2b$10$sKKl3o5U3WzIjQ9.IAzjE.O0pVaeDRLPy/i3b9Nb8.E3Dd1OuN2B2', 1),
    ('8944328-8', '$2b$10$smTan0dJ7ZKqXaiwuxu9MOFPSus5qjJXdX.zwkDI8TVCA3r54Q/ym', 1),
    ('8296535-1', '$2b$10$VVLRGYzWx/M5hb3XBP8OWetJ3mlNulj1mvbpacX4t64xo8mgDpeAC', 1),
    ('8907231-K', '$2b$10$49RwPvaoU.ogvM3kCpHG1OtqvFPvkH3GtpK.CPz/OcwyCEMCzyp26', 1),
    ('8868423-0', '$2b$10$FHhpdVPU2xPmJ4KO6KvvzOy1IfSmQsr08zDjkksJK9ihfcTIjcQv.', 1),
    ('8187746-7', '$2b$10$PfcJ1O8HeL19gsXjPIUpJOS0TnwkXmFzXXR0OBnnnbpWz8i71LQU.', 1),
    ('8792658-3', '$2b$10$gU4ue7VesQAQYf4F36guGubo/xdoKG1BTQq0gKx1WyEaVNKGeda3W', 1),
    ('8991668-2', '$2b$10$L/9j0HRCIiUpZBhinXqN/eEyVVw3AghckvoBaNKEfIcBO8rLknMeu', 1),
    ('8662316-1', '$2b$10$sVRha0jUncEwcmCXVIMkneRxUi7OTRSoFH9PhTU/6RkcHlg2NAL9a', 1),
    ('8025989-1', '$2b$10$S3C2nTaIHCoBBcn.7/PYheVVnXCcJV3K961jYYmdqhjuF3pGi/wxy', 1),
    ('8247016-6', '$2b$10$eFAgOwNDQeokwiJBSkk2ZOs3Hhcm.9CGVQfrdbcEU9RjaqCViVnMy', 1),
    ('8398481-3', '$2b$10$bGCAx7HK5McRikb9r6bJwenMRH/cSpEsmB/rEps2SO5ElWoYvxW36', 1);

-- 40 pacientes
INSERT INTO Paciente (RUT_Paciente, Nombre_Completo, Fecha_Nacimiento, Sexo, Telefono, Email, ID_Comuna_Residencia, ID_Prevision) VALUES
    ('8539253-0', 'Emilia Martínez Reyes', '2000-05-03', 'F', '+56947763270', 'emilia.martinez@mail.com', 7, 1),
    ('8836544-5', 'Carolina Valenzuela Rodríguez', '1948-03-22', 'F', '+56953649304', 'carolina.valenzuela@mail.com', 1, 2),
    ('8846765-5', 'Esteban Carrasco Herrera', '1952-12-19', 'M', '+56961167851', 'esteban.carrasco@mail.com', 10, 2),
    ('8253074-6', 'Amanda Soto Vergara', '1999-06-09', 'F', '+56913723608', 'amanda.soto@mail.com', 9, 1),
    ('8402929-7', 'Benjamín Fuentes González', '2010-10-12', 'M', '+56920236402', 'benjamin.fuentes@mail.com', 1, 3),
    ('8306546-K', 'Sofía Soto Morales', '2003-06-06', 'F', '+56978691617', 'sofia.soto@mail.com', 4, 1),
    ('8163779-2', 'Francisco Sepúlveda Bravo', '1959-12-20', 'M', '+56989547778', 'francisco.sepulveda@mail.com', 6, 2),
    ('8919172-6', 'Esteban Castro Morales', '2008-08-23', 'M', '+56944065595', 'esteban.castro@mail.com', 11, 1),
    ('8185951-5', 'Isidora Reyes Muñoz', '1940-12-19', 'F', '+56933047925', 'isidora.reyes@mail.com', 12, 1),
    ('8460385-6', 'Nicolás Gutiérrez Silva', '1950-08-30', 'Otro', '+56957940019', 'nicolas.gutierrez@mail.com', 2, 1),
    ('8415050-9', 'Gabriel Reyes Gutiérrez', '1980-06-07', 'M', '+56944708889', 'gabriel.reyes@mail.com', 7, 1),
    ('8768980-8', 'Daniela Morales Silva', '2004-09-11', 'F', '+56928790419', 'daniela.morales@mail.com', 5, 2),
    ('8677381-3', 'Francisco Tapia López', '1942-08-27', 'M', '+56966157175', 'francisco.tapia@mail.com', 3, 2),
    ('8655594-8', 'Matías Rodríguez Pérez', '1966-07-13', 'M', NULL, 'matias.rodriguez@mail.com', 3, 2),
    ('8410968-1', 'Nicolás Castro Tapia', '2013-02-25', 'M', '+56954821237', 'nicolas.castro@mail.com', 11, 2),
    ('8262779-0', 'Rodrigo Tapia Hernández', '2005-09-09', 'M', '+56942896866', 'rodrigo.tapia@mail.com', 3, 1),
    ('8732159-2', 'Tomás López Araya', '1996-09-29', 'Otro', '+56939598829', 'tomas.lopez@mail.com', 11, 1),
    ('8297342-7', 'Paula Rojas Bravo', '1943-09-18', 'F', '+56919441999', 'paula.rojas@mail.com', 10, 1),
    ('8452753-K', 'Gabriel Fuentes López', '1949-12-03', 'M', '+56984634668', 'gabriel.fuentes@mail.com', 3, 1),
    ('8288495-5', 'Camila Ramírez Hernández', '1991-02-22', 'F', '+56947673145', 'camila.ramirez@mail.com', 7, 1),
    ('8983368-K', 'Esteban Ramírez Gutiérrez', '2016-05-03', 'M', '+56940203364', 'esteban.ramirez@mail.com', 7, 2),
    ('8140795-9', 'Martina Espinoza Fuentes', '1965-05-01', 'F', '+56955035241', 'martina.espinoza@mail.com', 10, 3),
    ('8887895-7', 'Diego Torres Rojas', '1968-12-23', 'M', '+56949494477', 'diego.torres@mail.com', 11, 2),
    ('8153041-6', 'Álvaro Hernández Valenzuela', '1942-04-23', 'M', '+56958424746', 'alvaro.hernandez@mail.com', 1, 2),
    ('8132763-7', 'Constanza Vásquez Carrasco', '1949-06-05', 'F', '+56999762399', 'constanza.vasquez@mail.com', 12, 1),
    ('8220479-2', 'Martina Bravo Contreras', '1991-03-10', 'F', '+56993353333', 'martina.bravo@mail.com', 4, 1),
    ('8107952-8', 'Joaquín Pérez Gutiérrez', '2013-05-10', 'M', '+56913320683', 'joaquin.perez@mail.com', 4, 1),
    ('8376054-0', 'Constanza Espinoza Sepúlveda', '2000-05-30', 'F', '+56966106195', 'constanza.espinoza@mail.com', 9, 2),
    ('8802216-5', 'Catalina Silva Vásquez', '2003-07-09', 'F', '+56933342490', 'catalina.silva@mail.com', 4, 3),
    ('8944328-8', 'Trinidad Soto Castro', '1965-07-08', 'F', NULL, 'trinidad.soto@mail.com', 12, 3),
    ('8296535-1', 'Camila Espinoza Martínez', '2013-09-02', 'F', NULL, 'camila.espinoza@mail.com', 1, 1),
    ('8907231-K', 'Josefa Díaz Vergara', '1988-03-30', 'F', '+56943985671', 'josefa.diaz@mail.com', 8, 3),
    ('8868423-0', 'Ignacio Araya López', '1967-06-26', 'M', '+56952117276', 'ignacio.araya@mail.com', 5, 1),
    ('8187746-7', 'Francisco González Bravo', '1986-11-29', 'M', '+56991824940', 'francisco.gonzalez@mail.com', 8, 2),
    ('8792658-3', 'Felipe Morales Fuentes', '1948-01-20', 'M', NULL, 'felipe.morales@mail.com', 4, 2),
    ('8991668-2', 'Álvaro Pérez López', '1944-07-14', 'M', '+56943188220', 'alvaro.perez@mail.com', 11, 1),
    ('8662316-1', 'Ignacia Reyes Silva', '1962-11-26', 'F', '+56983938608', 'ignacia.reyes@mail.com', 9, 3),
    ('8025989-1', 'Paula Sepúlveda Castro', '1940-11-29', 'F', '+56956987212', 'paula.sepulveda@mail.com', 9, 1),
    ('8247016-6', 'Andrés Díaz López', '2006-10-04', 'M', NULL, 'andres.diaz@mail.com', 7, 2),
    ('8398481-3', 'Felipe Tapia Espinoza', '2010-12-01', 'M', '+56972956937', 'felipe.tapia@mail.com', 7, 1);

-- 15 médicos
INSERT INTO Medico (RUT_Medico, Nombre_Completo, Email_Institucional) VALUES
('9664386-1', 'Renata Herrera Torres', 'rtorres@saludusm.cl'),
('9620344-6', 'Gabriel Pérez Muñoz', 'gmunoz@saludusm.cl'),
('9269774-6', 'Florencia Castro Torres', 'ftorres@saludusm.cl'),
('9548556-1', 'Emilia Bravo Vásquez', 'evasquez@saludusm.cl'),
('9561704-2', 'Tomás Flores Díaz', 'tdiaz@saludusm.cl'),
('9067300-9', 'Pedro Castro Pérez', 'pperez@saludusm.cl'),
('9873251-9', 'Ignacio Vásquez Rojas', 'irojas@saludusm.cl'),
('9814665-2', 'Diego Carrasco Díaz', 'ddiaz@saludusm.cl'),
('9745702-6', 'Tomás Fuentes Flores', 'tflores@saludusm.cl'),
('9378098-1', 'Gabriel Gutiérrez Martínez', 'gmartinez@saludusm.cl'),
('9992053-K', 'Francisco Herrera Fuentes', 'ffuentes@saludusm.cl'),
('9188995-1', 'Daniela Pérez Rojas', 'drojas@saludusm.cl'),
('9600077-4', 'Cristóbal Soto Martínez', 'cmartinez@saludusm.cl'),
('9454803-9', 'Sebastián Hernández Soto', 'ssoto@saludusm.cl'),
('9318463-7', 'Tomás Vásquez Pérez', 'tperez@saludusm.cl');

-- Especialidades por médico (1 a 3)
INSERT INTO Medico_Especialidad (RUT_Medico, ID_Especialidad) VALUES
('9664386-1', 9),
('9620344-6', 11),
('9269774-6', 4),
('9548556-1', 18),
('9548556-1', 32),
('9561704-2', 17),
('9561704-2', 10),
('9561704-2', 8),
('9067300-9', 35),
('9873251-9', 11),
('9873251-9', 9),
('9873251-9', 6),
('9814665-2', 3),
('9814665-2', 21),
('9745702-6', 20),
('9745702-6', 3),
('9378098-1', 4),
('9992053-K', 8),
('9992053-K', 34),
('9188995-1', 10),
('9188995-1', 17),
('9188995-1', 3),
('9600077-4', 13),
('9454803-9', 6),
('9454803-9', 24),
('9318463-7', 35),
('9318463-7', 24),
('9318463-7', 25);

-- Centros por médico
INSERT INTO Medico_Centro (RUT_Medico, Codigo_Centro) VALUES
('9664386-1', 'CM-PRO-01'),
('9664386-1', 'CM-STG-01'),
('9620344-6', 'CM-VAL-01'),
('9269774-6', 'CM-VIN-01'),
('9548556-1', 'CM-STG-01'),
('9548556-1', 'CM-VIN-01'),
('9561704-2', 'CM-PRO-01'),
('9067300-9', 'CM-STG-01'),
('9873251-9', 'CM-STG-01'),
('9873251-9', 'CM-VIN-01'),
('9814665-2', 'CM-PRO-01'),
('9814665-2', 'CM-VAL-01'),
('9745702-6', 'CM-PRO-01'),
('9378098-1', 'CM-VAL-01'),
('9992053-K', 'CM-STG-01'),
('9188995-1', 'CM-STG-01'),
('9188995-1', 'CM-PRO-01'),
('9600077-4', 'CM-VAL-01'),
('9454803-9', 'CM-STG-01'),
('9318463-7', 'CM-VIN-01'),
('9318463-7', 'CM-PRO-01');

-- Catálogo de diagnósticos CIE-10 (20)
INSERT INTO Diagnostico (Codigo_CIE10, Descripcion) VALUES
('J00', 'Rinofaringitis aguda (resfriado común)'),
('I10', 'Hipertensión esencial (primaria)'),
('E11', 'Diabetes mellitus tipo 2'),
('J06', 'Infección aguda de las vías respiratorias superiores'),
('K29', 'Gastritis y duodenitis'),
('M54', 'Dorsalgia'),
('J45', 'Asma'),
('F41', 'Otros trastornos de ansiedad'),
('N39', 'Infección de vías urinarias'),
('R51', 'Cefalea'),
('L20', 'Dermatitis atópica'),
('H52', 'Trastornos de la acomodación y de la refracción'),
('M17', 'Gonartrosis'),
('I25', 'Enfermedad isquémica crónica del corazón'),
('J20', 'Bronquitis aguda'),
('E66', 'Obesidad'),
('F32', 'Episodio depresivo'),
('H10', 'Conjuntivitis'),
('R10', 'Dolor abdominal y pélvico'),
('Z00', 'Examen médico general');

-- 120 citas históricas y futuras (IDs 1..120; las atenciones las referencian por ID)
INSERT INTO Cita (Fecha_Hora, RUT_Paciente, RUT_Medico, Codigo_Centro, ID_Especialidad, ID_Estado_Cita) VALUES
('2026-06-26 09:30:00', '8185951-5', '9188995-1', 'CM-STG-01', 10, 3),
('2026-08-24 15:15:00', '8655594-8', '9269774-6', 'CM-VIN-01', 4, 3),
('2026-11-09 15:30:00', '8132763-7', '9664386-1', 'CM-STG-01', 9, 2),
('2026-06-24 15:45:00', '8247016-6', '9745702-6', 'CM-PRO-01', 20, 3),
('2026-11-05 08:30:00', '8288495-5', '9318463-7', 'CM-VIN-01', 35, 1),
('2026-09-03 17:30:00', '8253074-6', '9454803-9', 'CM-STG-01', 6, 3),
('2026-09-02 16:45:00', '8983368-K', '9548556-1', 'CM-VIN-01', 32, 3),
('2026-03-06 09:45:00', '8655594-8', '9664386-1', 'CM-PRO-01', 9, 3),
('2026-05-05 12:30:00', '8539253-0', '9188995-1', 'CM-PRO-01', 17, 2),
('2026-06-10 17:30:00', '8991668-2', '9378098-1', 'CM-VAL-01', 4, 2),
('2026-06-15 12:30:00', '8153041-6', '9454803-9', 'CM-STG-01', 6, 3),
('2026-03-30 16:30:00', '8107952-8', '9067300-9', 'CM-STG-01', 35, 3),
('2026-01-19 17:15:00', '8662316-1', '9873251-9', 'CM-VIN-01', 11, 5),
('2026-04-03 10:45:00', '8153041-6', '9561704-2', 'CM-PRO-01', 8, 4),
('2026-03-20 11:00:00', '8288495-5', '9454803-9', 'CM-STG-01', 24, 1),
('2026-08-11 11:30:00', '8983368-K', '9188995-1', 'CM-PRO-01', 3, 5),
('2026-06-19 10:15:00', '8792658-3', '9873251-9', 'CM-VIN-01', 9, 4),
('2026-04-13 10:45:00', '8253074-6', '9992053-K', 'CM-STG-01', 34, 3),
('2026-02-13 15:30:00', '8452753-K', '9378098-1', 'CM-VAL-01', 4, 3),
('2026-07-27 10:30:00', '8153041-6', '9378098-1', 'CM-VAL-01', 4, 4),
('2026-08-07 11:45:00', '8220479-2', '9318463-7', 'CM-PRO-01', 35, 3),
('2026-03-26 13:45:00', '8919172-6', '9454803-9', 'CM-STG-01', 6, 3),
('2026-05-13 15:00:00', '8919172-6', '9561704-2', 'CM-PRO-01', 10, 4),
('2026-01-22 08:15:00', '8132763-7', '9992053-K', 'CM-STG-01', 8, 5),
('2026-04-01 09:45:00', '8185951-5', '9548556-1', 'CM-VIN-01', 18, 3),
('2026-04-13 11:45:00', '8768980-8', '9269774-6', 'CM-VIN-01', 4, 3),
('2026-10-09 09:15:00', '8662316-1', '9745702-6', 'CM-PRO-01', 3, 2),
('2026-07-08 10:45:00', '8919172-6', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-10-23 10:15:00', '8944328-8', '9745702-6', 'CM-PRO-01', 3, 1),
('2026-06-25 12:30:00', '8132763-7', '9814665-2', 'CM-VAL-01', 21, 5),
('2026-10-09 12:15:00', '8792658-3', '9318463-7', 'CM-VIN-01', 24, 1),
('2026-07-17 17:15:00', '8677381-3', '9664386-1', 'CM-STG-01', 9, 5),
('2026-03-17 10:00:00', '8983368-K', '9269774-6', 'CM-VIN-01', 4, 1),
('2026-05-04 10:15:00', '8836544-5', '9454803-9', 'CM-STG-01', 6, 3),
('2026-07-22 08:45:00', '8153041-6', '9992053-K', 'CM-STG-01', 8, 5),
('2026-05-27 12:00:00', '8792658-3', '9548556-1', 'CM-VIN-01', 32, 2),
('2026-06-18 14:00:00', '8107952-8', '9992053-K', 'CM-STG-01', 34, 3),
('2026-02-03 14:00:00', '8732159-2', '9992053-K', 'CM-STG-01', 8, 3),
('2026-09-18 08:00:00', '8296535-1', '9620344-6', 'CM-VAL-01', 11, 1),
('2026-10-01 16:45:00', '8410968-1', '9561704-2', 'CM-PRO-01', 10, 2),
('2026-05-13 08:30:00', '8655594-8', '9454803-9', 'CM-STG-01', 24, 5),
('2026-04-21 14:15:00', '8887895-7', '9454803-9', 'CM-STG-01', 24, 4),
('2026-08-19 11:45:00', '8253074-6', '9620344-6', 'CM-VAL-01', 11, 3),
('2026-09-18 15:30:00', '8025989-1', '9992053-K', 'CM-STG-01', 34, 2),
('2026-05-26 16:15:00', '8768980-8', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-02-24 10:15:00', '8247016-6', '9454803-9', 'CM-STG-01', 24, 3),
('2026-01-13 10:00:00', '8253074-6', '9873251-9', 'CM-STG-01', 11, 3),
('2026-04-16 17:15:00', '8297342-7', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-05-20 11:45:00', '8185951-5', '9664386-1', 'CM-PRO-01', 9, 1),
('2026-04-10 12:45:00', '8220479-2', '9745702-6', 'CM-PRO-01', 3, 3),
('2026-09-25 12:30:00', '8187746-7', '9454803-9', 'CM-STG-01', 6, 1),
('2026-04-06 16:15:00', '8220479-2', '9745702-6', 'CM-PRO-01', 3, 1),
('2026-04-01 17:15:00', '8415050-9', '9620344-6', 'CM-VAL-01', 11, 3),
('2026-01-20 17:15:00', '8153041-6', '9269774-6', 'CM-VIN-01', 4, 3),
('2026-04-27 11:00:00', '8132763-7', '9814665-2', 'CM-PRO-01', 21, 4),
('2026-03-16 17:00:00', '8460385-6', '9454803-9', 'CM-STG-01', 6, 4),
('2026-03-20 13:30:00', '8655594-8', '9814665-2', 'CM-VAL-01', 21, 3),
('2026-03-25 15:30:00', '8868423-0', '9454803-9', 'CM-STG-01', 6, 3),
('2026-01-16 15:45:00', '8460385-6', '9188995-1', 'CM-PRO-01', 10, 3),
('2026-09-02 09:15:00', '8887895-7', '9745702-6', 'CM-PRO-01', 3, 3),
('2026-01-14 16:45:00', '8887895-7', '9067300-9', 'CM-STG-01', 35, 4),
('2026-02-27 15:00:00', '8944328-8', '9745702-6', 'CM-PRO-01', 20, 3),
('2026-04-03 11:45:00', '8402929-7', '9814665-2', 'CM-PRO-01', 21, 4),
('2026-06-15 09:45:00', '8802216-5', '9600077-4', 'CM-VAL-01', 13, 4),
('2026-03-20 14:45:00', '8220479-2', '9561704-2', 'CM-PRO-01', 17, 3),
('2026-05-18 12:00:00', '8983368-K', '9561704-2', 'CM-PRO-01', 8, 3),
('2026-08-17 17:45:00', '8187746-7', '9378098-1', 'CM-VAL-01', 4, 3),
('2026-10-21 15:30:00', '8140795-9', '9318463-7', 'CM-PRO-01', 25, 1),
('2026-01-15 09:30:00', '8732159-2', '9188995-1', 'CM-STG-01', 17, 3),
('2026-03-30 09:00:00', '8140795-9', '9454803-9', 'CM-STG-01', 24, 3),
('2026-06-01 15:30:00', '8288495-5', '9188995-1', 'CM-PRO-01', 17, 3),
('2026-06-23 10:30:00', '8868423-0', '9378098-1', 'CM-VAL-01', 4, 3),
('2026-05-26 17:30:00', '8732159-2', '9664386-1', 'CM-PRO-01', 9, 3),
('2026-03-31 14:30:00', '8452753-K', '9454803-9', 'CM-STG-01', 24, 3),
('2026-01-21 09:30:00', '8297342-7', '9664386-1', 'CM-PRO-01', 9, 2),
('2026-05-19 14:00:00', '8539253-0', '9548556-1', 'CM-VIN-01', 18, 4),
('2026-05-20 16:15:00', '8944328-8', '9992053-K', 'CM-STG-01', 34, 3),
('2026-10-20 16:15:00', '8163779-2', '9992053-K', 'CM-STG-01', 34, 2),
('2026-08-10 11:30:00', '8376054-0', '9620344-6', 'CM-VAL-01', 11, 4),
('2026-09-09 17:00:00', '8539253-0', '9873251-9', 'CM-VIN-01', 9, 2),
('2026-07-10 11:00:00', '8944328-8', '9548556-1', 'CM-VIN-01', 18, 3),
('2026-09-25 09:45:00', '8376054-0', '9664386-1', 'CM-PRO-01', 9, 1),
('2026-02-19 11:00:00', '8887895-7', '9620344-6', 'CM-VAL-01', 11, 3),
('2026-01-07 13:45:00', '8376054-0', '9067300-9', 'CM-STG-01', 35, 5),
('2026-03-13 11:15:00', '8732159-2', '9664386-1', 'CM-PRO-01', 9, 3),
('2026-10-05 12:00:00', '8107952-8', '9269774-6', 'CM-VIN-01', 4, 2),
('2026-05-26 13:00:00', '8220479-2', '9814665-2', 'CM-PRO-01', 21, 2),
('2026-03-13 15:00:00', '8768980-8', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-09-08 17:30:00', '8132763-7', '9745702-6', 'CM-PRO-01', 3, 1),
('2026-04-20 10:00:00', '8887895-7', '9188995-1', 'CM-STG-01', 17, 3),
('2026-08-31 15:15:00', '8398481-3', '9454803-9', 'CM-STG-01', 6, 3),
('2026-03-04 10:30:00', '8452753-K', '9454803-9', 'CM-STG-01', 24, 3),
('2026-08-24 09:30:00', '8132763-7', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-09-23 14:30:00', '8402929-7', '9873251-9', 'CM-STG-01', 9, 2),
('2026-03-30 09:15:00', '8107952-8', '9992053-K', 'CM-STG-01', 8, 3),
('2026-07-01 12:15:00', '8415050-9', '9664386-1', 'CM-STG-01', 9, 3),
('2026-07-31 14:30:00', '8802216-5', '9620344-6', 'CM-VAL-01', 11, 3),
('2026-03-16 13:45:00', '8539253-0', '9873251-9', 'CM-STG-01', 6, 4),
('2026-05-08 12:45:00', '8107952-8', '9067300-9', 'CM-STG-01', 35, 3),
('2026-05-20 15:00:00', '8677381-3', '9620344-6', 'CM-VAL-01', 11, 3),
('2026-03-04 14:00:00', '8398481-3', '9745702-6', 'CM-PRO-01', 3, 3),
('2026-04-23 10:15:00', '8802216-5', '9992053-K', 'CM-STG-01', 8, 3),
('2026-04-28 08:00:00', '8153041-6', '9269774-6', 'CM-VIN-01', 4, 3),
('2026-11-13 08:45:00', '8187746-7', '9561704-2', 'CM-PRO-01', 10, 1),
('2026-08-19 13:15:00', '8887895-7', '9873251-9', 'CM-STG-01', 11, 3),
('2026-08-24 10:00:00', '8732159-2', '9664386-1', 'CM-STG-01', 9, 4),
('2026-08-18 16:00:00', '8297342-7', '9188995-1', 'CM-STG-01', 17, 3),
('2026-11-04 14:45:00', '8288495-5', '9814665-2', 'CM-VAL-01', 21, 1),
('2026-01-21 12:45:00', '8402929-7', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-06-23 13:45:00', '8768980-8', '9269774-6', 'CM-VIN-01', 4, 1),
('2026-07-02 11:15:00', '8247016-6', '9600077-4', 'CM-VAL-01', 13, 3),
('2026-04-13 13:45:00', '8907231-K', '9664386-1', 'CM-STG-01', 9, 3),
('2026-07-13 08:45:00', '8907231-K', '9188995-1', 'CM-STG-01', 17, 3),
('2026-11-09 16:30:00', '8662316-1', '9814665-2', 'CM-PRO-01', 3, 2),
('2026-06-29 09:45:00', '8919172-6', '9814665-2', 'CM-PRO-01', 21, 4),
('2026-10-29 15:00:00', '8247016-6', '9269774-6', 'CM-VIN-01', 4, 2),
('2026-09-03 14:45:00', '8185951-5', '9561704-2', 'CM-PRO-01', 17, 5),
('2026-01-12 12:45:00', '8262779-0', '9664386-1', 'CM-PRO-01', 9, 3),
('2026-08-14 11:15:00', '8677381-3', '9378098-1', 'CM-VAL-01', 4, 5),
('2026-08-26 12:45:00', '8662316-1', '9067300-9', 'CM-STG-01', 35, 3);

-- 8 citas futuras adicionales (IDs 121..128) para probar reprogramar, cancelar y agendar
INSERT INTO Cita (Fecha_Hora, RUT_Paciente, RUT_Medico, Codigo_Centro, ID_Especialidad, ID_Estado_Cita) VALUES
    ('2026-11-17 10:00:00', '8539253-0', '9188995-1', 'CM-STG-01', 17, 1),
    ('2026-11-18 15:30:00', '8539253-0', '9664386-1', 'CM-PRO-01', 9, 2),
    ('2026-11-19 09:15:00', '8253074-6', '9188995-1', 'CM-PRO-01', 10, 1),
    ('2026-11-20 11:45:00', '8402929-7', '9188995-1', 'CM-STG-01', 3, 1),
    ('2026-11-23 16:00:00', '8836544-5', '9873251-9', 'CM-VIN-01', 9, 1),
    ('2026-11-24 08:30:00', '8846765-5', '9548556-1', 'CM-STG-01', 18, 2),
    ('2026-11-25 14:00:00', '8398481-3', '9561704-2', 'CM-PRO-01', 17, 1),
    ('2026-12-01 10:30:00', '8539253-0', '9188995-1', 'CM-PRO-01', 10, 1);

-- Atenciones (solo para citas en estado Atendida)
INSERT INTO Atencion (Motivo_Consulta, Observaciones_Clinicas, ID_Cita) VALUES
('Control de presión arterial', 'Se deriva a especialista para evaluación adicional.', 1),
('Consulta por síntomas alérgicos', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 2),
('Dolor persistente', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 4),
('Control de rutina', 'Sin hallazgos relevantes al examen físico.', 6),
('Seguimiento de tratamiento', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 7),
('Dolor abdominal', 'Se deriva a especialista para evaluación adicional.', 8),
('Consulta por síntomas alérgicos', 'Sin hallazgos relevantes al examen físico.', 11),
('Dolor abdominal', 'Se solicitan exámenes complementarios.', 12),
('Fiebre y malestar general', 'Paciente estable, se indica tratamiento ambulatorio.', 18),
('Consulta por cefalea recurrente', 'Se deriva a especialista para evaluación adicional.', 19),
('Control de presión arterial', 'Sin hallazgos relevantes al examen físico.', 21),
('Control de presión arterial', 'Paciente estable, se indica tratamiento ambulatorio.', 22),
('Dolor abdominal', 'Paciente estable, se indica tratamiento ambulatorio.', 25),
('Dolor abdominal', 'Se deriva a especialista para evaluación adicional.', 26),
('Evaluación de lesión articular', 'Sin hallazgos relevantes al examen físico.', 28),
('Seguimiento de tratamiento', 'Paciente estable, se indica tratamiento ambulatorio.', 34),
('Dolor persistente', 'Se deriva a especialista para evaluación adicional.', 37),
('Chequeo por síntomas respiratorios', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 38),
('Consulta por cefalea recurrente', 'Paciente estable, se indica tratamiento ambulatorio.', 43),
('Control de presión arterial', 'Buena evolución respecto a control anterior.', 45),
('Control de presión arterial', 'Buena evolución respecto a control anterior.', 46),
('Consulta por cefalea recurrente', 'Se solicitan exámenes complementarios.', 47),
('Evaluación de lesión articular', 'Se deriva a especialista para evaluación adicional.', 48),
('Evaluación de lesión articular', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 50),
('Consulta por cefalea recurrente', 'Se ajusta dosis de medicamento habitual.', 53),
('Dolor persistente', 'Buena evolución respecto a control anterior.', 54),
('Seguimiento de tratamiento', 'Sin hallazgos relevantes al examen físico.', 57),
('Seguimiento de tratamiento', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 58),
('Chequeo por síntomas respiratorios', 'Se solicitan exámenes complementarios.', 59),
('Chequeo por síntomas respiratorios', 'Se solicitan exámenes complementarios.', 60),
('Consulta por cefalea recurrente', 'Paciente estable, se indica tratamiento ambulatorio.', 62),
('Consulta por síntomas alérgicos', 'Se deriva a especialista para evaluación adicional.', 65),
('Control de rutina', 'Buena evolución respecto a control anterior.', 66),
('Control de rutina', 'Se solicitan exámenes complementarios.', 67),
('Control de presión arterial', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 69),
('Consulta por cefalea recurrente', 'Se ajusta dosis de medicamento habitual.', 70),
('Consulta por síntomas alérgicos', 'Se solicitan exámenes complementarios.', 71),
('Control de presión arterial', 'Se deriva a especialista para evaluación adicional.', 72),
('Dolor abdominal', 'Sin hallazgos relevantes al examen físico.', 73),
('Consulta por cefalea recurrente', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 74),
('Control de presión arterial', 'Sin hallazgos relevantes al examen físico.', 77),
('Consulta por cefalea recurrente', 'Buena evolución respecto a control anterior.', 81),
('Consulta por síntomas alérgicos', 'Se solicitan exámenes complementarios.', 83),
('Chequeo por síntomas respiratorios', 'Se deriva a especialista para evaluación adicional.', 85),
('Evaluación de lesión articular', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 88),
('Dolor abdominal', 'Sin hallazgos relevantes al examen físico.', 90),
('Fiebre y malestar general', 'Se ajusta dosis de medicamento habitual.', 91),
('Seguimiento de tratamiento', 'Se ajusta dosis de medicamento habitual.', 92),
('Control de rutina', 'Sin hallazgos relevantes al examen físico.', 93),
('Chequeo por síntomas respiratorios', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 95),
('Evaluación de lesión articular', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 96),
('Control de presión arterial', 'Se solicitan exámenes complementarios.', 97),
('Evaluación de lesión articular', 'Sin hallazgos relevantes al examen físico.', 99),
('Chequeo por síntomas respiratorios', 'Se deriva a especialista para evaluación adicional.', 100),
('Seguimiento de tratamiento', 'Se deriva a especialista para evaluación adicional.', 101),
('Evaluación de lesión articular', 'Se deriva a especialista para evaluación adicional.', 102),
('Control de presión arterial', 'Se solicitan exámenes complementarios.', 103),
('Control de presión arterial', 'Paciente estable, se indica tratamiento ambulatorio.', 105),
('Control de presión arterial', 'Se refuerzan indicaciones de autocuidado y control en 30 días.', 107),
('Evaluación de lesión articular', 'Paciente estable, se indica tratamiento ambulatorio.', 109),
('Consulta por síntomas alérgicos', 'Sin hallazgos relevantes al examen físico.', 111),
('Control de presión arterial', 'Se deriva a especialista para evaluación adicional.', 112),
('Chequeo por síntomas respiratorios', 'Sin hallazgos relevantes al examen físico.', 113),
('Control de rutina', 'Se solicitan exámenes complementarios.', 118),
('Control de presión arterial', 'Se solicitan exámenes complementarios.', 120);

-- Diagnósticos por atención
INSERT INTO Atencion_Diagnostico (ID_Atencion, Codigo_CIE10) VALUES
(1, 'E66'),
(1, 'F41'),
(2, 'F32'),
(3, 'N39'),
(4, 'E11'),
(5, 'Z00'),
(6, 'R51'),
(7, 'F41'),
(8, 'F41'),
(9, 'N39'),
(10, 'F32'),
(11, 'M54'),
(11, 'F41'),
(12, 'N39'),
(12, 'J00'),
(13, 'R51'),
(13, 'J06'),
(14, 'J06'),
(14, 'M54'),
(15, 'H10'),
(16, 'L20'),
(17, 'F32'),
(18, 'E11'),
(19, 'J20'),
(20, 'J06'),
(21, 'H52'),
(22, 'F32'),
(23, 'J06'),
(24, 'K29'),
(25, 'H52'),
(26, 'J00'),
(27, 'J06'),
(27, 'L20'),
(28, 'F41'),
(29, 'M17'),
(29, 'K29'),
(30, 'M54'),
(31, 'E11'),
(32, 'I10'),
(33, 'H10'),
(34, 'J45'),
(34, 'Z00'),
(35, 'L20'),
(36, 'R51'),
(37, 'K29'),
(38, 'E11'),
(39, 'K29'),
(39, 'H10'),
(40, 'J20'),
(41, 'E11'),
(42, 'M17'),
(43, 'E11'),
(44, 'R10'),
(44, 'H10'),
(45, 'N39'),
(45, 'K29'),
(46, 'H10'),
(47, 'I25'),
(48, 'I10'),
(49, 'M17'),
(50, 'M54'),
(51, 'J20'),
(52, 'J45'),
(53, 'L20'),
(53, 'H10'),
(54, 'M17'),
(55, 'J45'),
(56, 'E66'),
(56, 'R51'),
(57, 'E66'),
(58, 'F41'),
(59, 'N39'),
(60, 'M17'),
(61, 'I25'),
(61, 'H52'),
(62, 'M17'),
(63, 'L20'),
(64, 'R10'),
(65, 'N39');

-- Recetas por atención
INSERT INTO Receta (Medicamento, Dosis, Dias_Tratamiento, ID_Atencion) VALUES
('Amoxicilina 500mg', '500 mg cada 8 horas', 3, 2),
('Losartán 50mg', '1 aplicación cada 8 horas si hay dolor', 30, 4),
('Ácido Acetilsalicílico 100mg', '20 mg cada 24 horas', 5, 5),
('Omeprazol 20mg', '1 comprimido cada 12 horas', 14, 5),
('Amoxicilina 500mg', '500 mg cada 8 horas', 5, 5),
('Loratadina 10mg', '1 aplicación cada 8 horas si hay dolor', 3, 6),
('Furosemida 40mg', '500 mg cada 8 horas', 5, 8),
('Loratadina 10mg', '1 comprimido cada 12 horas', 10, 9),
('Prednisona 20mg', '500 mg cada 8 horas', 30, 11),
('Clonazepam 0.5mg', '5 mg cada 12 horas', 30, 12),
('Naproxeno 250mg', '1 aplicación cada 8 horas si hay dolor', 7, 12),
('Diclofenaco 50mg', '5 mg cada 12 horas', 7, 13),
('Amoxicilina 500mg', '500 mg cada 8 horas', 5, 13),
('Loratadina 10mg', '5 mg cada 12 horas', 7, 14),
('Enalapril 10mg', '20 mg cada 24 horas', 10, 15),
('Ibuprofeno 400mg', '500 mg cada 8 horas', 30, 16),
('Azitromicina 500mg', '10 mg una vez al día', 10, 17),
('Amoxicilina 500mg', '1 comprimido cada 12 horas', 10, 17),
('Paracetamol 500mg', '1 aplicación cada 8 horas si hay dolor', 10, 18),
('Sertralina 50mg', '1 comprimido cada 12 horas', 7, 19),
('Diclofenaco 50mg', '500 mg cada 8 horas', 14, 20),
('Sertralina 50mg', '10 mg una vez al día', 7, 21),
('Losartán 50mg', '10 mg una vez al día', 10, 21),
('Clonazepam 0.5mg', '5 mg cada 12 horas', 30, 23),
('Enalapril 10mg', '5 mg cada 12 horas', 10, 23),
('Prednisona 20mg', '1 comprimido cada 12 horas', 5, 25),
('Furosemida 40mg', '20 mg cada 24 horas', 7, 26),
('Paracetamol 500mg', '1 comprimido cada 12 horas', 3, 27),
('Enalapril 10mg', '20 mg cada 24 horas', 10, 27),
('Omeprazol 20mg', '20 mg cada 24 horas', 30, 28),
('Amoxicilina + Ácido Clavulánico 875mg', '20 mg cada 24 horas', 7, 28),
('Naproxeno 250mg', '10 mg una vez al día', 7, 30),
('Omeprazol 20mg', '20 mg cada 24 horas', 30, 30),
('Atorvastatina 20mg', '20 mg cada 24 horas', 30, 31),
('Omeprazol 20mg', '500 mg cada 8 horas', 10, 31),
('Atorvastatina 20mg', '1 aplicación cada 8 horas si hay dolor', 10, 32),
('Ácido Acetilsalicílico 100mg', '500 mg cada 8 horas', 7, 33),
('Sertralina 50mg', '20 mg cada 24 horas', 14, 34),
('Clonazepam 0.5mg', '1 comprimido cada 12 horas', 30, 35),
('Azitromicina 500mg', '10 mg una vez al día', 5, 35),
('Losartán 50mg', '500 mg cada 8 horas', 30, 36),
('Atorvastatina 20mg', '10 mg una vez al día', 30, 36),
('Salbutamol inhalador', '10 mg una vez al día', 30, 37),
('Azitromicina 500mg', '1 comprimido cada 12 horas', 3, 37),
('Omeprazol 20mg', '20 mg cada 24 horas', 14, 38),
('Naproxeno 250mg', '1 aplicación cada 8 horas si hay dolor', 30, 38),
('Salbutamol inhalador', '20 mg cada 24 horas', 14, 38),
('Furosemida 40mg', '20 mg cada 24 horas', 30, 39),
('Salbutamol inhalador', '1 aplicación cada 8 horas si hay dolor', 14, 39),
('Atorvastatina 20mg', '500 mg cada 8 horas', 3, 41),
('Sertralina 50mg', '1 aplicación cada 8 horas si hay dolor', 10, 41),
('Amoxicilina + Ácido Clavulánico 875mg', '500 mg cada 8 horas', 3, 42),
('Furosemida 40mg', '1 comprimido cada 12 horas', 10, 43),
('Azitromicina 500mg', '10 mg una vez al día', 10, 44),
('Losartán 50mg', '500 mg cada 8 horas', 30, 44),
('Paracetamol 500mg', '20 mg cada 24 horas', 3, 45),
('Enalapril 10mg', '1 comprimido cada 12 horas', 3, 46),
('Cetirizina 10mg', '10 mg una vez al día', 30, 47),
('Paracetamol 500mg', '20 mg cada 24 horas', 7, 47),
('Naproxeno 250mg', '500 mg cada 8 horas', 14, 47),
('Ibuprofeno 400mg', '5 mg cada 12 horas', 7, 48),
('Ácido Acetilsalicílico 100mg', '5 mg cada 12 horas', 30, 49),
('Ácido Acetilsalicílico 100mg', '20 mg cada 24 horas', 3, 49),
('Azitromicina 500mg', '1 comprimido cada 12 horas', 5, 49),
('Ácido Acetilsalicílico 100mg', '500 mg cada 8 horas', 7, 50),
('Diclofenaco 50mg', '1 comprimido cada 12 horas', 3, 50),
('Diclofenaco 50mg', '1 comprimido cada 12 horas', 5, 51),
('Amoxicilina 500mg', '20 mg cada 24 horas', 30, 51),
('Sertralina 50mg', '10 mg una vez al día', 7, 52),
('Sertralina 50mg', '500 mg cada 8 horas', 3, 52),
('Sertralina 50mg', '1 comprimido cada 12 horas', 5, 52),
('Loratadina 10mg', '500 mg cada 8 horas', 7, 54),
('Cetirizina 10mg', '20 mg cada 24 horas', 5, 54),
('Metformina 850mg', '1 aplicación cada 8 horas si hay dolor', 7, 54),
('Amoxicilina + Ácido Clavulánico 875mg', '500 mg cada 8 horas', 7, 55),
('Diclofenaco 50mg', '1 comprimido cada 12 horas', 3, 55),
('Amoxicilina 500mg', '20 mg cada 24 horas', 30, 56),
('Losartán 50mg', '1 aplicación cada 8 horas si hay dolor', 14, 56),
('Diclofenaco 50mg', '1 aplicación cada 8 horas si hay dolor', 30, 56),
('Enalapril 10mg', '1 aplicación cada 8 horas si hay dolor', 3, 57),
('Prednisona 20mg', '500 mg cada 8 horas', 7, 57),
('Loratadina 10mg', '20 mg cada 24 horas', 10, 58),
('Metformina 850mg', '1 comprimido cada 12 horas', 14, 58),
('Omeprazol 20mg', '10 mg una vez al día', 3, 59),
('Metformina 850mg', '500 mg cada 8 horas', 14, 59),
('Losartán 50mg', '1 comprimido cada 12 horas', 5, 59),
('Atorvastatina 20mg', '500 mg cada 8 horas', 5, 60),
('Cetirizina 10mg', '500 mg cada 8 horas', 5, 60),
('Prednisona 20mg', '10 mg una vez al día', 5, 61),
('Metformina 850mg', '1 comprimido cada 12 horas', 10, 61),
('Enalapril 10mg', '5 mg cada 12 horas', 3, 61),
('Ibuprofeno 400mg', '20 mg cada 24 horas', 10, 63),
('Metformina 850mg', '1 comprimido cada 12 horas', 30, 63),
('Atorvastatina 20mg', '500 mg cada 8 horas', 30, 64),
('Enalapril 10mg', '5 mg cada 12 horas', 7, 65),
('Amoxicilina + Ácido Clavulánico 875mg', '1 aplicación cada 8 horas si hay dolor', 5, 65);


-- ============================================================
-- FUNCIONES
-- ============================================================
DELIMITER //

-- Porcentaje de citas "No Asistió" sobre el total de citas del centro (Panel de gestión)
CREATE FUNCTION fn_pct_inasistencia(p_codigo_centro VARCHAR(20))
RETURNS DECIMAL(5,2)
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_no INT DEFAULT 0;

    SELECT COUNT(*) INTO v_total FROM Cita WHERE Codigo_Centro = p_codigo_centro;
    IF v_total = 0 THEN
        RETURN 0.00;
    END IF;

    SELECT COUNT(*) INTO v_no
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.Codigo_Centro = p_codigo_centro AND e.Nombre = 'No Asistió';

    RETURN ROUND(v_no * 100.0 / v_total, 2);
END//

-- Valida todas las reglas de negocio de un horario (Agendar y Reprogramar).
-- Devuelve NULL si el horario es válido, o el mensaje del primer error encontrado.
-- p_id_cita_excluir: al reprogramar, la propia cita no debe contar como conflicto (NULL al agendar).
CREATE FUNCTION fn_validar_horario(
    p_rut_paciente VARCHAR(10),
    p_rut_medico VARCHAR(10),
    p_codigo_centro VARCHAR(20),
    p_id_especialidad INT,
    p_fecha_hora DATETIME,
    p_id_cita_excluir INT
)
RETURNS VARCHAR(255)
READS SQL DATA
BEGIN
    DECLARE v_n INT DEFAULT 0;

    IF p_fecha_hora <= NOW() THEN
        RETURN 'La fecha y hora deben ser posteriores al momento actual.';
    END IF;
    IF WEEKDAY(p_fecha_hora) > 4 THEN
        RETURN 'Solo se atiende de lunes a viernes.';
    END IF;
    IF TIME(p_fecha_hora) < '08:00:00' OR TIME(p_fecha_hora) > '17:45:00' THEN
        RETURN 'El horario de atención es de 08:00 a 18:00 (último bloque a las 17:45).';
    END IF;
    IF MINUTE(p_fecha_hora) % 15 <> 0 OR SECOND(p_fecha_hora) <> 0 THEN
        RETURN 'Las citas se agendan en bloques de 15 minutos (:00, :15, :30, :45).';
    END IF;

    SELECT COUNT(*) INTO v_n FROM Medico_Especialidad
     WHERE RUT_Medico = p_rut_medico AND ID_Especialidad = p_id_especialidad;
    IF v_n = 0 THEN
        RETURN 'El médico no posee la especialidad solicitada.';
    END IF;

    SELECT COUNT(*) INTO v_n FROM Medico_Centro
     WHERE RUT_Medico = p_rut_medico AND Codigo_Centro = p_codigo_centro;
    IF v_n = 0 THEN
        RETURN 'El médico no atiende en el centro seleccionado.';
    END IF;

    SELECT COUNT(*) INTO v_n
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.RUT_Medico = p_rut_medico
       AND c.Fecha_Hora = p_fecha_hora
       AND e.Nombre <> 'Cancelada'
       AND (p_id_cita_excluir IS NULL OR c.ID_Cita <> p_id_cita_excluir);
    IF v_n > 0 THEN
        RETURN 'El médico ya tiene una cita en ese horario.';
    END IF;

    SELECT COUNT(*) INTO v_n
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.RUT_Paciente = p_rut_paciente
       AND c.Fecha_Hora = p_fecha_hora
       AND e.Nombre <> 'Cancelada'
       AND (p_id_cita_excluir IS NULL OR c.ID_Cita <> p_id_cita_excluir);
    IF v_n > 0 THEN
        RETURN 'El paciente ya tiene otra cita a esa misma fecha y hora.';
    END IF;

    RETURN NULL;
END//

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS
-- p_ok = 1 si la operación se realizó, 0 si no; p_mensaje explica el motivo.
-- ============================================================

-- Agendar hora (paciente)
CREATE PROCEDURE sp_agendar_cita(
    IN p_rut_paciente VARCHAR(10),
    IN p_rut_medico VARCHAR(10),
    IN p_codigo_centro VARCHAR(20),
    IN p_id_especialidad INT,
    IN p_fecha_hora DATETIME,
    OUT p_id_cita INT,
    OUT p_ok TINYINT,
    OUT p_mensaje VARCHAR(255)
)
proc: BEGIN
    DECLARE v_n INT DEFAULT 0;
    DECLARE v_lock VARCHAR(10);
    DECLARE v_error VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_id_cita = NULL;
        SET p_ok = 0;
        SET p_mensaje = 'Error inesperado al agendar la cita.';
    END;

    SET p_id_cita = NULL;
    SET p_ok = 0;

    SELECT COUNT(*) INTO v_n FROM Paciente WHERE RUT_Paciente = p_rut_paciente;
    IF v_n = 0 THEN
        SET p_mensaje = 'El paciente no existe.';
        LEAVE proc;
    END IF;

    START TRANSACTION;
    -- Bloquea la fila del médico para que dos agendamientos simultáneos no pisen el mismo bloque
    SELECT RUT_Medico INTO v_lock FROM Medico WHERE RUT_Medico = p_rut_medico FOR UPDATE;
    IF v_lock IS NULL THEN
        ROLLBACK;
        SET p_mensaje = 'El médico no existe.';
        LEAVE proc;
    END IF;

    SET v_error = fn_validar_horario(p_rut_paciente, p_rut_medico, p_codigo_centro,
                                     p_id_especialidad, p_fecha_hora, NULL);
    IF v_error IS NOT NULL THEN
        ROLLBACK;
        SET p_mensaje = v_error;
        LEAVE proc;
    END IF;

    INSERT INTO Cita (Fecha_Hora, RUT_Paciente, RUT_Medico, Codigo_Centro, ID_Especialidad, ID_Estado_Cita)
    VALUES (p_fecha_hora, p_rut_paciente, p_rut_medico, p_codigo_centro, p_id_especialidad,
            (SELECT ID_Estado_Cita FROM Estado_Cita WHERE Nombre = 'Reservada'));
    SET p_id_cita = LAST_INSERT_ID();
    COMMIT;

    SET p_ok = 1;
    SET p_mensaje = 'Cita agendada correctamente.';
END//

-- Reprogramar (paciente): solo citas Reservada/Confirmada que aún no han pasado.
-- La cita vuelve a estado Reservada (el médico debe confirmarla de nuevo).
CREATE PROCEDURE sp_reprogramar_cita(
    IN p_id_cita INT,
    IN p_rut_paciente VARCHAR(10),
    IN p_nueva_fecha_hora DATETIME,
    OUT p_ok TINYINT,
    OUT p_mensaje VARCHAR(255)
)
proc: BEGIN
    DECLARE v_rut_pac VARCHAR(10);
    DECLARE v_rut_med VARCHAR(10);
    DECLARE v_centro VARCHAR(20);
    DECLARE v_esp INT;
    DECLARE v_fecha DATETIME;
    DECLARE v_estado VARCHAR(50);
    DECLARE v_lock VARCHAR(10);
    DECLARE v_error VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_ok = 0;
        SET p_mensaje = 'Error inesperado al reprogramar la cita.';
    END;

    SET p_ok = 0;

    START TRANSACTION;
    SELECT c.RUT_Paciente, c.RUT_Medico, c.Codigo_Centro, c.ID_Especialidad, c.Fecha_Hora, e.Nombre
      INTO v_rut_pac, v_rut_med, v_centro, v_esp, v_fecha, v_estado
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.ID_Cita = p_id_cita
       FOR UPDATE;

    IF v_rut_pac IS NULL OR v_rut_pac <> p_rut_paciente THEN
        ROLLBACK;
        SET p_mensaje = 'La cita no existe o no pertenece al paciente.';
        LEAVE proc;
    END IF;
    IF v_estado NOT IN ('Reservada', 'Confirmada') THEN
        ROLLBACK;
        SET p_mensaje = 'Solo se pueden reprogramar citas en estado Reservada o Confirmada.';
        LEAVE proc;
    END IF;
    IF v_fecha < NOW() THEN
        ROLLBACK;
        SET p_mensaje = 'No se puede reprogramar una cita cuya fecha ya pasó.';
        LEAVE proc;
    END IF;
    IF v_fecha = p_nueva_fecha_hora THEN
        ROLLBACK;
        SET p_mensaje = 'La nueva fecha y hora es igual a la actual.';
        LEAVE proc;
    END IF;

    SELECT RUT_Medico INTO v_lock FROM Medico WHERE RUT_Medico = v_rut_med FOR UPDATE;

    SET v_error = fn_validar_horario(v_rut_pac, v_rut_med, v_centro, v_esp, p_nueva_fecha_hora, p_id_cita);
    IF v_error IS NOT NULL THEN
        ROLLBACK;
        SET p_mensaje = v_error;
        LEAVE proc;
    END IF;

    UPDATE Cita
       SET Fecha_Hora = p_nueva_fecha_hora,
           ID_Estado_Cita = (SELECT ID_Estado_Cita FROM Estado_Cita WHERE Nombre = 'Reservada')
     WHERE ID_Cita = p_id_cita;
    COMMIT;

    SET p_ok = 1;
    SET p_mensaje = 'Cita reprogramada correctamente.';
END//

-- Cancelar (paciente): solo citas Reservada/Confirmada que aún no han pasado
CREATE PROCEDURE sp_cancelar_cita(
    IN p_id_cita INT,
    IN p_rut_paciente VARCHAR(10),
    OUT p_ok TINYINT,
    OUT p_mensaje VARCHAR(255)
)
proc: BEGIN
    DECLARE v_rut_pac VARCHAR(10);
    DECLARE v_fecha DATETIME;
    DECLARE v_estado VARCHAR(50);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_ok = 0;
        SET p_mensaje = 'Error inesperado al cancelar la cita.';
    END;

    SET p_ok = 0;

    START TRANSACTION;
    SELECT c.RUT_Paciente, c.Fecha_Hora, e.Nombre
      INTO v_rut_pac, v_fecha, v_estado
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.ID_Cita = p_id_cita
       FOR UPDATE;

    IF v_rut_pac IS NULL OR v_rut_pac <> p_rut_paciente THEN
        ROLLBACK;
        SET p_mensaje = 'La cita no existe o no pertenece al paciente.';
        LEAVE proc;
    END IF;
    IF v_estado NOT IN ('Reservada', 'Confirmada') THEN
        ROLLBACK;
        SET p_mensaje = 'Solo se pueden cancelar citas en estado Reservada o Confirmada.';
        LEAVE proc;
    END IF;
    IF v_fecha < NOW() THEN
        ROLLBACK;
        SET p_mensaje = 'No se puede cancelar una cita cuya fecha ya pasó.';
        LEAVE proc;
    END IF;

    UPDATE Cita
       SET ID_Estado_Cita = (SELECT ID_Estado_Cita FROM Estado_Cita WHERE Nombre = 'Cancelada')
     WHERE ID_Cita = p_id_cita;
    COMMIT;

    SET p_ok = 1;
    SET p_mensaje = 'Cita cancelada correctamente.';
END//

-- Citas vencidas -> Cancelada (equivale a la consulta 10 de la Tarea 1).
-- "Vencida" = Reservada/Confirmada con fecha anterior al día de hoy (así el médico
-- puede registrar estados durante todo el día de la cita).
CREATE PROCEDURE sp_cancelar_citas_vencidas(OUT p_canceladas INT)
BEGIN
    UPDATE Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
       SET c.ID_Estado_Cita = (SELECT ID_Estado_Cita FROM Estado_Cita WHERE Nombre = 'Cancelada')
     WHERE e.Nombre IN ('Reservada', 'Confirmada')
       AND c.Fecha_Hora < CURDATE();
    SET p_canceladas = ROW_COUNT();
END//

-- ============================================================
-- TRIGGERS
-- ============================================================

-- Un médico puede tener como máximo 3 especialidades
CREATE TRIGGER trg_medico_especialidad_bi
BEFORE INSERT ON Medico_Especialidad
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM Medico_Especialidad WHERE RUT_Medico = NEW.RUT_Medico) >= 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un médico no puede tener más de 3 especialidades.';
    END IF;
END//

-- No se puede quitar una especialidad si es la última del médico o si tiene citas futuras con ella
CREATE TRIGGER trg_medico_especialidad_bd
BEFORE DELETE ON Medico_Especialidad
FOR EACH ROW
BEGIN
    DECLARE v_n INT DEFAULT 0;

    SELECT COUNT(*) INTO v_n FROM Medico_Especialidad WHERE RUT_Medico = OLD.RUT_Medico;
    IF v_n <= 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un médico debe tener al menos una especialidad.';
    END IF;

    SELECT COUNT(*) INTO v_n
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.RUT_Medico = OLD.RUT_Medico
       AND c.ID_Especialidad = OLD.ID_Especialidad
       AND c.Fecha_Hora >= NOW()
       AND e.Nombre IN ('Reservada', 'Confirmada');
    IF v_n > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede quitar la especialidad: el médico tiene citas futuras asociadas.';
    END IF;
END//

-- No se puede quitar un centro si es el último del médico o si tiene citas futuras en él
CREATE TRIGGER trg_medico_centro_bd
BEFORE DELETE ON Medico_Centro
FOR EACH ROW
BEGIN
    DECLARE v_n INT DEFAULT 0;

    SELECT COUNT(*) INTO v_n FROM Medico_Centro WHERE RUT_Medico = OLD.RUT_Medico;
    IF v_n <= 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un médico debe atender en al menos un centro.';
    END IF;

    SELECT COUNT(*) INTO v_n
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.RUT_Medico = OLD.RUT_Medico
       AND c.Codigo_Centro = OLD.Codigo_Centro
       AND c.Fecha_Hora >= NOW()
       AND e.Nombre IN ('Reservada', 'Confirmada');
    IF v_n > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede quitar el centro: el médico tiene citas futuras en él.';
    END IF;
END//

-- Solo puede existir una atención si la cita está en estado Atendida
CREATE TRIGGER trg_atencion_bi
BEFORE INSERT ON Atencion
FOR EACH ROW
BEGIN
    DECLARE v_estado VARCHAR(50);

    SELECT e.Nombre INTO v_estado
      FROM Cita c
      JOIN Estado_Cita e ON e.ID_Estado_Cita = c.ID_Estado_Cita
     WHERE c.ID_Cita = NEW.ID_Cita;

    IF v_estado IS NULL OR v_estado <> 'Atendida' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Solo se puede registrar una atención si la cita está en estado Atendida.';
    END IF;
END//

-- Una cita que ya tiene atención no puede salir del estado Atendida
CREATE TRIGGER trg_cita_bu
BEFORE UPDATE ON Cita
FOR EACH ROW
BEGIN
    IF OLD.ID_Estado_Cita <> NEW.ID_Estado_Cita
       AND NEW.ID_Estado_Cita <> (SELECT ID_Estado_Cita FROM Estado_Cita WHERE Nombre = 'Atendida')
       AND EXISTS (SELECT 1 FROM Atencion WHERE ID_Cita = OLD.ID_Cita) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La cita ya tiene una atención registrada: debe permanecer en estado Atendida.';
    END IF;
END//

DELIMITER ;

-- ============================================================
-- VIEWS
-- ============================================================

-- Detalle de citas: alimenta "Mis citas", "Agenda" y la búsqueda avanzada
CREATE VIEW v_citas_detalle AS
SELECT
    c.ID_Cita,
    c.Fecha_Hora,
    DATE(c.Fecha_Hora) AS Fecha,
    TIME(c.Fecha_Hora) AS Hora,
    p.RUT_Paciente,
    p.Nombre_Completo AS Paciente,
    pr.ID_Prevision,
    pr.Nombre AS Prevision,
    m.RUT_Medico,
    m.Nombre_Completo AS Medico,
    esp.ID_Especialidad,
    esp.Nombre AS Especialidad,
    cm.Codigo_Centro,
    cm.Nombre AS Centro,
    co.ID_Comuna,
    co.Nombre AS Comuna,
    r.ID_Region,
    r.Nombre AS Region,
    ec.ID_Estado_Cita,
    ec.Nombre AS Estado
FROM Cita c
JOIN Paciente p       ON p.RUT_Paciente = c.RUT_Paciente
JOIN Prevision pr     ON pr.ID_Prevision = p.ID_Prevision
JOIN Medico m         ON m.RUT_Medico = c.RUT_Medico
JOIN Especialidad esp ON esp.ID_Especialidad = c.ID_Especialidad
JOIN Centro_Medico cm ON cm.Codigo_Centro = c.Codigo_Centro
JOIN Comuna co        ON co.ID_Comuna = cm.ID_Comuna
JOIN Region r         ON r.ID_Region = co.ID_Region
JOIN Estado_Cita ec   ON ec.ID_Estado_Cita = c.ID_Estado_Cita;

-- Médicos con sus especialidades y centros: alimenta la barra de búsqueda
CREATE VIEW v_medicos_detalle AS
SELECT
    m.RUT_Medico,
    m.Nombre_Completo AS Medico,
    m.Email_Institucional,
    (SELECT GROUP_CONCAT(e.Nombre ORDER BY e.Nombre SEPARATOR ' | ')
       FROM Medico_Especialidad me
       JOIN Especialidad e ON e.ID_Especialidad = me.ID_Especialidad
      WHERE me.RUT_Medico = m.RUT_Medico) AS Especialidades,
    (SELECT GROUP_CONCAT(cm.Nombre ORDER BY cm.Nombre SEPARATOR ' | ')
       FROM Medico_Centro mc
       JOIN Centro_Medico cm ON cm.Codigo_Centro = mc.Codigo_Centro
      WHERE mc.RUT_Medico = m.RUT_Medico) AS Centros
FROM Medico m;

-- Panel de gestión: total de citas y % de inasistencia por centro
CREATE VIEW v_panel_centros AS
SELECT
    cm.Codigo_Centro,
    cm.Nombre AS Centro,
    COUNT(c.ID_Cita) AS Total_Citas,
    COALESCE(SUM(ec.Nombre = 'No Asistió'), 0) AS Citas_No_Asistio,
    fn_pct_inasistencia(cm.Codigo_Centro) AS Pct_Inasistencia
FROM Centro_Medico cm
LEFT JOIN Cita c         ON c.Codigo_Centro = cm.Codigo_Centro
LEFT JOIN Estado_Cita ec ON ec.ID_Estado_Cita = c.ID_Estado_Cita
GROUP BY cm.Codigo_Centro, cm.Nombre;


-- ============================================================
-- Verificación rápida de la carga
-- ============================================================
SELECT 'Usuario' AS tabla, COUNT(*) AS total FROM Usuario
UNION ALL SELECT 'Paciente', COUNT(*) FROM Paciente
UNION ALL SELECT 'Medico', COUNT(*) FROM Medico
UNION ALL SELECT 'Centro_Medico', COUNT(*) FROM Centro_Medico
UNION ALL SELECT 'Cita', COUNT(*) FROM Cita
UNION ALL SELECT 'Atencion', COUNT(*) FROM Atencion
UNION ALL SELECT 'Diagnostico', COUNT(*) FROM Diagnostico
UNION ALL SELECT 'Receta', COUNT(*) FROM Receta;
