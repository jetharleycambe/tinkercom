<!-- LOGIN MODAL -->
<div class="login-modal" id="loginModalDisplay">
    <div class="log-in">
        <form action="login.php" method="POST" class="login-form" id="login-form">
            <h1>Welcome to Tinkercom</h1>
            <h3>Login</h3>
            <p class="error-message"></p>
            <div>
                <label for="log-username" id="login-lbl-user">Username/Email</label>
                <input id="log-username" type="text" name="username" />
            </div>
            <div>
                <label for="log-pass" id="login-lbl-pass">Password</label>
                <input id="log-pass" type="password" name="password" />
            </div>
            <div>
                <button id="log-in-btn" type="submit">Login</button>
            </div>
            <div>
                <p id="log-to-reg">
                    Don't have an account yet?
                    <a href="#" onclick="openRegisterModal()">Create your account.</a>
                </p>
            </div>
        </form>
    </div>
</div>



<!-- REGISTER MODAL -->
<div class="register-modal" id="registerModalDisplay">
    <div class="register">
        <form action="register.php" method="POST" class="reg-form" id="reg-form">
            <h1>Welcome to Tinkercom</h1>
            <h3>Create your Account</h3>
            <p class="error-message"></p>
            <div>
                <label for="reg-username">Username</label>
                <input id="reg-username" type="text" name="username" />
            </div>
            <div>
                <label for="reg-email">Email</label>
                <input id="reg-email" type="email" name="email" />
            </div>
            <div>
                <label for="reg-pass" id="reg-lbl-pass">Password</label>
                <input id="reg-pass" type="password" name="password" />
                <p class="strength-text"></p>
                <ul class="password-hints">
                    <li id="len">At least 8 characters</li>
                    <li id="upper">At least 1 uppercase letter</li>
                    <li id="num">At least 1 number</li>
                    <li id="sym">At least 1 special character</li>
                </ul>
            </div>
            <div>
                <label for="reg-conpass" id="reg-lbl-conpass">Confirm Password</label>
                <input id="reg-conpass" type="password" name="confirm_password" />
            </div>
            <div>
                <button id="register-btn" type="submit">Register</button>
            </div>
            <div>
                <p id="reg-to-log">
                    Already have an account?
                    <a href="#" onclick="openLoginModal()">Login.</a>
                </p>
            </div>
        </form>
    </div>
</div>


