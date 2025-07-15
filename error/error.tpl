<title id="title">{$error_title}</title>
<style id="style">
    .error-container {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        max-height: calc(var(--70vh));
        font-family: 'Roboto', sans-serif;
    }

    .error-container .display-medium {
        margin-bottom: 2rem;
        color: rgba(var(--mdui-color-primary));
    }
    @media (max-width: 768px) {
        .error-container .display-medium {
            font-size: 2rem;
        }
        .error-container .display-small {
            font-size: 1.5rem;
        }
    }
    .error-container p {
        margin-bottom: 1rem;
        color: rgba(var(--mdui-color-secondary));
    }

</style>


<div id="container" class="container">

    <div class="error-container">
        <p class="display-medium">{$error_title}</p>
        <p class="display-small">{$error_message}</p>
        <p class="display-small">{$error_sub_message}</p>
        <p class="display-small mt-2">
            <a href="javascript:history.back();" style="color: rgba(var(--mdui-color-primary)); text-decoration: none;">
                返回上一页
            </a>
        </p>
    </div>

</div>

<script id="script">
    window.pageLoadFiles = [];
    window.pageOnLoad = function (loading) {
        return false
    };
</script>