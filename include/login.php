<?php
    if (empty($included)) die('No access');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css"> <!-- Путь к CSS файлу -->
    <link rel="icon" href="img/academ_photo-resizer.ru.png" type="image/png">

    <title>Модуль входа в систему</title>
</head>

    <style>
        .fadeout {
            opacity: 1;
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
            transform: translateY(0);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: white; 
            z-index: 9999; 
        }

        .fadeout.hidden {
            opacity: 0;
            transform: translateY(-20px);
        }
    </style>


<body>
    <div class="background-image" id="background-image-1"></div>
    <div class="background-image" id="background-image-2"></div>
    <div class="foreground-image"></div> <!-- Изображение поверх фона -->
    <div class="logovuz-image"></div> <!-- Логотип института -->
    <div class="logoezk-image"></div> <!-- Логотип ЭЗК -->
    <div class="logo-copirait"></div> <!-- Логотип копирайта -->
    <div class="logo-pocta"></div> <!-- Логотип почты-->
    <div class="login-container">
        <h1>Добро пожаловать в Электронную зачетную книжку</h1>
        <h2>Пожалуйста, войдите в свой аккаунт</h2>
        <form id="login-form" action="" method="post">
            <div>
                <label for="login">Логин (номер пропуска):</label>
                <input type="text" id="login" name="login" placeholder="Введите свой номер пропуска">
                <span class="info-icon" onclick="toggleInfoTooltip()"></span>
                <div class="info-tooltip" id="info-tooltip">Номер пропуска указан внизу его пластиковой пропускной карты в виде девятизначного числа</div>
            </div>
            <div>
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" placeholder="Введите пароль">
            </div>
            <button type="submit">Войти</button>
        </form>
        <div>
            <a href="?">Забыли пароль?</a>
        </div>
    </div>
    <div class="contact">
        <p>portal@miigaik.ru</p>
    </div>
    <div class="contact2">
        <p>2024, Московский государственный университет геодезии и картографии</p>
    </div>
    <script>
        function toggleInfoTooltip() {
            var tooltip = document.getElementById('info-tooltip');
            tooltip.style.display = tooltip.style.display === 'none' ? 'block' : 'none';
        }

        window.onload = function() {
        setTimeout(function() {
            document.querySelector('.fadeout').classList.add('hidden');
        }, 1000); // Задержка в миллисекундах
    };
    </script>

<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Получение данных из формы
    
    if (!empty($_POST['login'])) {
        $login = $conn->real_escape_string($_POST['login']);
    } else {
        $login = '';
    }
    
    if (!empty($_POST['password'])) {
        $password = $conn->real_escape_string($_POST['password']);
    } else {
        $password = '';
    }
    
    // Проверяем, есть ли такой пользователь в таблице users
    $sql = "SELECT * FROM students WHERE loginS='$login' AND pass='$password'";
    
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Авторизация прошла успешно для пользователя
        // Добавляем id студента в сессию
        $row = $result->fetch_assoc();
        
        $user = [
            'type'          => 'student', 
            'student_id'    => $row['student_id'],
            'full_name'     => $row['full_name'],
            'group_id'      => $row['group_id'],
        ];
        
        $_SESSION['user'] = $user;
            
        echo "<div class='fadeout'>"; // Добавляем класс с анимацией
        echo "<script>
                setTimeout(function() {
                    document.querySelector('.fadeout').classList.add('hidden');
                    setTimeout(function() {
                            window.location.reload();
//                        window.location.href = 'http://localhost/student.php';
                    }, 500); // Задержка в миллисекундах
                }, 10); // Задержка перед началом анимации
              </script>";
        echo "</div>";
        exit();
    } else {
        // Проверяем, есть ли такой пользователь в таблице teacher
        $sql = "SELECT * FROM teachers WHERE loginT='$login' AND pass='$password'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            // Авторизация прошла успешно для преподавателя
            $row = $result->fetch_assoc();
            
            $user = [
                'type'          => 'teacher', 
                'teacher_id'    => $row['teacher_id'], // Сохраняем id преподавателя в сессии
                'teacher_name'  => $row['full_name'], // Сохраняем имя учителя в сессии
            ];
            
            $_SESSION['user'] = $user;
            
            echo "<div class='fadeout'>"; // Добавляем класс с анимацией
            echo "<script>
                    setTimeout(function() {
                        document.querySelector('.fadeout').classList.add('hidden');
                        setTimeout(function() {
                            window.location.reload();
//                            window.location.href = 'http://localhost/prepodavatel.php';
                        }, 500); // Задержка в миллисекундах
                    }, 10); // Задержка перед началом анимации
                  </script>";
            echo "</div>";
            exit();
        } else {
            // Неверные логин или пароль для обоих таблиц
            echo "<script>alert('Неверные логин или пароль. Попробуйте еще раз.');</script>";
        }
    }
    
    // Закрытие соединения с базой данных
    $conn->close();
}


?>

</body>
</html>
